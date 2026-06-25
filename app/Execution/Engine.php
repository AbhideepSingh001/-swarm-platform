<?php

declare(strict_types=1);

namespace App\Execution;

use App\Events\TaskCompleted;
use App\Events\TaskFailed;
use App\Events\TaskProgress;
use App\Events\TaskStarted;
use App\Models\Task;
use App\ValueObjects\ExecutionResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Engine
{
    public function __construct(
        private DriverRegistry $registry,
        private RetryManager $retryManager,
        private Sandbox $sandbox
    ) {}

    /**
     * Execute a task through its assigned driver.
     */
    public function run(Task $task): ExecutionResult
    {
        broadcast(new TaskStarted($task->id));

        DB::transaction(function () use ($task) {
            $task->update([
                'status' => 'running',
                'started_at' => now(),
                'attempts' => $task->attempts + 1,
            ]);
        });

        Log::info("Task {$task->id} started", [
            'driver' => $task->driver,
            'attempt' => $task->attempts,
        ]);

        try {
            $driver = $this->registry->get($task->driver);

            // Validate payload
            if (!$driver->validatePayload($task->payload)) {
                $result = new ExecutionResult(
                    success: false,
                    error: "Invalid payload for driver [{$task->driver}]. Check required fields."
                );
                $this->finalize($task, $result);
                return $result;
            }

            // Execute with retry
            $result = $this->retryManager->executeWithRetry(
                task: $task,
                callback: fn() => $driver->execute($task, [
                    'on_progress' => $this->makeProgressCallback($task),
                ]),
                maxAttempts: $driver->getMaxRetries(),
                retryableErrors: config("execution.drivers.{$task->driver}.retryable_errors", [])
            );

            $this->finalize($task, $result);
            return $result;

        } catch (\Throwable $e) {
            Log::error("Task {$task->id} crashed with unhandled exception", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $result = new ExecutionResult(
                success: false,
                error: "Engine crash: {$e->getMessage()}",
                exception: $e
            );

            $this->finalize($task, $result);
            return $result;
        }
    }

    /**
     * Create a progress callback for the task.
     */
    private function makeProgressCallback(Task $task): \Closure
    {
        return function (int $percent, string $message = '') use ($task): void {
            broadcast(new TaskProgress(
                taskId: $task->id,
                percent: $percent,
                message: $message
            ));

            // Throttle DB updates to avoid write pressure
            if ($percent % 10 === 0 || $percent === 100) {
                $task->update(['progress_percent' => $percent]);
            }
        };
    }

    /**
     * Finalize task state after execution.
     */
   private function finalize(Task $task, ExecutionResult $result): void
{
    $status = $result->success ? 'completed' : 'failed';

    DB::transaction(function () use ($task, $result, $status) {
        $task->update([
            'status' => $status,
            'completed_at' => now(),
            'result' => $result->output,        // was 'output'
            'last_error' => $result->error,      // was 'error'
            'metadata' => array_merge($task->metadata ?? [], $result->metadata),
            'retry_count' => $task->retry_count + 1,
            'actual_duration_minutes' => isset($result->metadata['duration_ms']) 
                ? round($result->metadata['duration_ms'] / 60000, 2) 
                : null,
        ]);
    });

    if ($result->success) {
        broadcast(new TaskCompleted($task->id, $result->toArray()));
    } else {
        broadcast(new TaskFailed($task->id, $result->error, $result->metadata));
    }

    Log::info("Task {$task->task_id} {$status}", [
        'duration_ms' => $result->metadata['duration_ms'] ?? null,
        'retry_count' => $task->retry_count,
    ]);
}

    /**
     * Dry-run: validate payload without executing.
     */
    public function validate(Task $task): array
    {
        try {
            $driver = $this->registry->get($task->driver);
            $valid = $driver->validatePayload($task->payload);

            return [
                'valid' => $valid,
                'driver' => $task->driver,
                'errors' => $valid ? [] : ['Invalid payload structure for driver'],
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'driver' => $task->driver,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Get resource requirements for a task.
     */
    public function getResourceRequirements(Task $task): array
    {
        try {
            $driver = $this->registry->get($task->driver);
            return $driver->getRequiredResources();
        } catch (\Throwable $e) {
            return [];
        }
    }
}