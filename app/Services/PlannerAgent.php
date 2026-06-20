<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\LLMException;

class PlannerAgent
{
    private GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function createPlan(string $goal, array $context = [], ?string $userId = null): Plan
    {
        return DB::transaction(function () use ($goal, $context, $userId) {
            $planData = $this->gemini->decomposeGoal($goal, $context);

            $plan = Plan::create([
                'title' => $planData['plan_title'],
                'description' => $planData['plan_description'],
                'goal' => $goal,
                'context' => $context,
                'status' => 'pending',
                'user_id' => $userId,
                'total_tasks' => $planData['metadata']['total_tasks'] ?? count($planData['tasks']),
                'estimated_duration_minutes' => $planData['metadata']['estimated_total_minutes'] ?? 0,
                'complexity' => $planData['metadata']['complexity'] ?? 'medium',
                'metadata' => $planData['metadata'] ?? [],
            ]);

            $taskMap = [];
            
            foreach ($planData['tasks'] as $taskData) {
                $task = Task::create([
                    'plan_id' => $plan->id,
                    'task_id' => $taskData['id'],
                    'title' => $taskData['title'],
                    'description' => $taskData['description'],
                    'priority' => $taskData['priority'],
                    'estimated_duration_minutes' => $taskData['estimated_duration_minutes'],
                    'agent_type' => $taskData['agent_type'],
                    'status' => 'pending',
                    'depends_on' => $taskData['depends_on'],
                ]);
                
                $taskMap[$taskData['id']] = $task->id;
            }

            foreach ($plan->tasks as $task) {
                $resolvedDeps = [];
                foreach ($task->depends_on as $depTaskId) {
                    if (isset($taskMap[$depTaskId])) {
                        $resolvedDeps[] = $taskMap[$depTaskId];
                    }
                }
                $task->update(['depends_on' => $resolvedDeps]);
            }

            Log::info('Plan created', [
                'plan_id' => $plan->id,
                'title' => $plan->title,
                'task_count' => $plan->tasks->count(),
            ]);

            return $plan->fresh('tasks');
        });
    }

    public function regeneratePlan(int $planId, array $feedback = []): Plan
    {
        $oldPlan = Plan::with('tasks')->findOrFail($planId);
        
        $context = array_merge($oldPlan->context ?? [], [
            'previous_plan' => $oldPlan->toArray(),
            'feedback' => $feedback,
            'instruction' => 'Regenerate this plan incorporating the feedback. Improve task breakdown, dependencies, and agent assignments.',
        ]);

        $oldPlan->tasks()->delete();
        $oldPlan->delete();

        return $this->createPlan($oldPlan->goal, $context, $oldPlan->user_id);
    }

    public function getExecutionOrder(int $planId): array
    {
        $plan = Plan::with('tasks')->findOrFail($planId);
        
        $tasks = $plan->tasks->keyBy('id')->toArray();
        $sorted = [];
        $inDegree = [];
        $adjList = [];

        foreach ($tasks as $id => $task) {
            $inDegree[$id] = 0;
            $adjList[$id] = [];
        }

        foreach ($tasks as $id => $task) {
            foreach ($task['depends_on'] as $depId) {
                if (isset($adjList[$depId])) {
                    $adjList[$depId][] = $id;
                    $inDegree[$id]++;
                }
            }
        }

        $queue = [];
        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $tasks[$current];

            foreach ($adjList[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sorted) !== count($tasks)) {
            throw new LLMException('Circular dependency detected in plan tasks');
        }

        return $sorted;
    }

    public function getReadyTasks(int $planId): array
    {
        $plan = Plan::with('tasks')->findOrFail($planId);
        
        $completedTasks = $plan->tasks
            ->where('status', 'completed')
            ->pluck('id')
            ->toArray();

        $readyTasks = $plan->tasks->filter(function ($task) use ($completedTasks) {
            if ($task->status !== 'pending') {
                return false;
            }
            foreach ($task->depends_on as $depId) {
                if (!in_array($depId, $completedTasks)) {
                    return false;
                }
            }
            return true;
        });

        return $readyTasks->values()->toArray();
    }
}