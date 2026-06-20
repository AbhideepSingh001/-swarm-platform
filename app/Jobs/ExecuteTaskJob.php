<?php

namespace App\Jobs;

use App\Agents\Executor\ExecutorAgent;
use App\Agents\Executor\ExecutionTask;
use App\Models\Task;
use App\Services\PlanExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $taskId;
    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(int $taskId)
    {
        $this->taskId = $taskId;
        $this->onQueue('executor');
    }

    public function handle(PlanExecutor $planExecutor, ExecutorAgent $executorAgent): void
    {
        $task = Task::find($this->taskId);
        
        if (!$task || $task->status !== Task::STATUS_QUEUED) {
            Log::warning('Task not found or not queued', ['task_id' => $this->taskId]);
            return;
        }

        if ($task->agent_type === Task::AGENT_EXECUTOR) {
            $this->runExecutorAgent($task, $executorAgent);
            return;
        }

        $this->runLegacyExecution($task, $planExecutor);
    }

    private function runExecutorAgent(Task $task, ExecutorAgent $executor): void
    {
        $task->markRunning();

        Log::info('Executing via ExecutorAgent', [
            'task_id' => $task->id,
            'title' => $task->title,
            'config' => $task->config,
        ]);

        try {
            $executionTask = new ExecutionTask(
                id: $task->id,
                type: $task->config['type'] ?? 'api_call',
                config: $task->config ?? [],
                maxRetries: 3,
            );

            $result = $executor->execute($executionTask);

            if ($result->success) {
                $task->update([
                    'status' => Task::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'result' => [
                        'output' => $result->output,
                        'metadata' => $result->metadata,
                        'execution_time' => $result->executionTime,
                    ],
                ]);
            } else {
                $task->update([
                    'status' => $task->retry_count < 3 ? Task::STATUS_QUEUED : Task::STATUS_FAILED,
                    'failed_at' => $task->retry_count >= 3 ? now() : null,
                    'retry_count' => $task->retry_count + 1,
                    'last_error' => $result->error,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('ExecutorAgent failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
            
            $task->update([
                'status' => $task->retry_count < 3 ? Task::STATUS_QUEUED : Task::STATUS_FAILED,
                'retry_count' => $task->retry_count + 1,
                'last_error' => $e->getMessage(),
            ]);
        }
    }

    private function runLegacyExecution(Task $task, PlanExecutor $executor): void
    {
        $task->markRunning();

        Log::info('Executing via PlanExecutor', [
            'task_id' => $task->id,
            'agent_type' => $task->agent_type,
        ]);

        try {
            $result = $this->simulateAgentExecution($task);
            $executor->taskCompleted($task->id, $result);

        } catch (\Exception $e) {
            Log::error('Legacy execution failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
            
            $executor->taskFailed($task->id, $e->getMessage());
        }
    }

    private function simulateAgentExecution(Task $task): array
    {
        return [
            'agent_type' => $task->agent_type,
            'task_title' => $task->title,
            'executed_at' => now()->toIso8601String(),
            'status' => 'success',
            'output' => "Simulated execution of {$task->title} by {$task->agent_type} agent",
        ];
    }

        public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteTaskJob failed permanently', [
            'task_id' => $this->taskId,
            'error' => $exception->getMessage(),
        ]);
    }
}