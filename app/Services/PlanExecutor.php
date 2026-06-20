<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use App\Jobs\ExecuteTaskJob;

class PlanExecutor
{
    private PlannerAgent $planner;

    public function __construct(PlannerAgent $planner)
    {
        $this->planner = $planner;
    }

    public function execute(int $planId): void
    {
        $plan = Plan::findOrFail($planId);
        
        if ($plan->status !== 'pending') {
            throw new \RuntimeException("Plan {$planId} is not in pending status (current: {$plan->status})");
        }

        $plan->update(['status' => 'running', 'started_at' => now()]);

        $this->dispatchReadyTasks($planId);

        Log::info('Plan execution started', ['plan_id' => $planId]);
    }

    public function dispatchReadyTasks(int $planId): void
    {
        $readyTasks = $this->planner->getReadyTasks($planId);

        foreach ($readyTasks as $task) {
            $taskModel = Task::find($task['id']);
            if ($taskModel && $taskModel->status === 'pending') {
                $taskModel->update(['status' => 'queued']);
                ExecuteTaskJob::dispatch($taskModel->id)->onQueue('agent-tasks');
            }
        }
    }

    public function taskCompleted(int $taskId, array $result = []): void
    {
        $task = Task::findOrFail($taskId);
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'result' => $result,
        ]);

        Log::info('Task completed', [
            'task_id' => $taskId,
            'plan_id' => $task->plan_id,
            'agent_type' => $task->agent_type,
        ]);

        $this->checkPlanCompletion($task->plan_id);
        $this->dispatchReadyTasks($task->plan_id);
    }

    public function taskFailed(int $taskId, string $error, bool $shouldRetry = true): void
    {
        $task = Task::findOrFail($taskId);
        $maxRetries = config('agents.executor.max_task_retries', 2);

        if ($shouldRetry && $task->retry_count < $maxRetries) {
            $task->update([
                'status' => 'pending',
                'retry_count' => $task->retry_count + 1,
                'last_error' => $error,
            ]);
            
            ExecuteTaskJob::dispatch($task->id)
                ->delay(now()->addSeconds(30 * ($task->retry_count + 1)))
                ->onQueue('agent-tasks');
            
            Log::warning('Task failed, retrying', [
                'task_id' => $taskId,
                'retry' => $task->retry_count + 1,
                'error' => $error,
            ]);
        } else {
            $task->update([
                'status' => 'failed',
                'last_error' => $error,
                'failed_at' => now(),
            ]);

            $this->failPlan($task->plan_id, "Task {$task->task_id} failed: {$error}");

            Log::error('Task failed permanently', [
                'task_id' => $taskId,
                'error' => $error,
            ]);
        }
    }

    private function checkPlanCompletion(int $planId): void
    {
        $plan = Plan::with('tasks')->find($planId);
        if (!$plan) return;

        $totalTasks = $plan->tasks->count();
        $completedTasks = $plan->tasks->where('status', 'completed')->count();

        if ($totalTasks > 0 && $totalTasks === $completedTasks) {
            $plan->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            Log::info('Plan completed', ['plan_id' => $planId]);
        }
    }

    private function failPlan(int $planId, string $reason): void
    {
        $plan = Plan::find($planId);
        if ($plan && $plan->status !== 'failed') {
            $plan->update([
                'status' => 'failed',
                'failure_reason' => $reason,
            ]);
            Log::error('Plan failed', ['plan_id' => $planId, 'reason' => $reason]);
        }
    }

    public function pause(int $planId): void
    {
        $plan = Plan::findOrFail($planId);
        if ($plan->status === 'running') {
            $plan->update(['status' => 'paused']);
            Log::info('Plan paused', ['plan_id' => $planId]);
        }
    }

    public function resume(int $planId): void
    {
        $plan = Plan::findOrFail($planId);
        if ($plan->status === 'paused') {
            $plan->update(['status' => 'running']);
            $this->dispatchReadyTasks($planId);
            Log::info('Plan resumed', ['plan_id' => $planId]);
        }
    }

    public function cancel(int $planId): void
    {
        $plan = Plan::findOrFail($planId);
        
        $plan->update(['status' => 'cancelled']);
        
        $plan->tasks()
            ->whereIn('status', ['pending', 'queued'])
            ->update(['status' => 'cancelled']);

        Log::info('Plan cancelled', ['plan_id' => $planId]);
    }

    public function getStatus(int $planId): array
    {
        $plan = Plan::with('tasks')->findOrFail($planId);
        
        $tasks = $plan->tasks;
        
        return [
            'plan_id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'progress' => [
                'total' => $tasks->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
                'running' => $tasks->where('status', 'running')->count(),
                'pending' => $tasks->where('status', 'pending')->count(),
                'failed' => $tasks->where('status', 'failed')->count(),
                'percentage' => $tasks->count() > 0 
                    ? round(($tasks->where('status', 'completed')->count() / $tasks->count()) * 100, 2)
                    : 0,
            ],
            'started_at' => $plan->started_at,
            'completed_at' => $plan->completed_at,
            'estimated_remaining_minutes' => $this->estimateRemainingTime($plan),
        ];
    }

    private function estimateRemainingTime(Plan $plan): int
    {
        $tasks = $plan->tasks;
        $completed = $tasks->where('status', 'completed');
        
        if ($completed->isEmpty()) {
            return $tasks->sum('estimated_duration_minutes');
        }

        $actualTotal = $completed->sum(function ($task) {
            if ($task->started_at && $task->completed_at) {
                return $task->started_at->diffInMinutes($task->completed_at);
            }
            return $task->estimated_duration_minutes;
        });

        $estimatedTotal = $tasks->sum('estimated_duration_minutes');
        $completedEstimated = $completed->sum('estimated_duration_minutes');
        
        if ($completedEstimated === 0) return $estimatedTotal;

        $ratio = $actualTotal / $completedEstimated;
        $remainingEstimated = $estimatedTotal - $completedEstimated;
        
        return (int) round($remainingEstimated * $ratio);
    }
}