<?php

namespace App\Agents\Executor;

use App\Agents\Executor\Handlers\TaskHandlerInterface;
use App\Models\TaskExecution;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ExecutorAgent
{
    /** @var Collection<TaskHandlerInterface> */
    private Collection $handlers;

    public function __construct(
        private readonly ResultReporter $reporter,
        TaskHandlerInterface ...$handlers
    ) {
        $this->handlers = collect($handlers);
    }

    public function registerHandler(TaskHandlerInterface $handler): void
    {
        $this->handlers->push($handler);
    }

    public function execute(ExecutionTask $task): ExecutionResult
    {
        $execution = TaskExecution::create([
            'task_id' => $task->id,
            'status' => 'pending',
            'max_attempts' => $task->maxRetries,
        ]);

        $execution->markRunning();

        try {
            $handler = $this->resolveHandler($task->type);

            if (!$handler) {
                $error = "No handler found for task type: {$task->type}";
                $execution->markFailed($error);
                $this->reporter->report($task, $execution);
                return ExecutionResult::failure($error);
            }

            Log::info('Executing task', [
                'task_id' => $task->id,
                'type' => $task->type,
                'handler' => $handler->getName(),
                'attempt' => $execution->attempt,
            ]);

            $result = $handler->execute($task);

            if ($result->success) {
                $execution->markCompleted($result->output, $result->metadata);
            } else {
                $execution->markFailed($result->error ?? 'Unknown error', $result->metadata);
            }

            $this->reporter->report($task, $execution);

            return $result;

        } catch (\Throwable $e) {
            Log::error('Executor Agent Critical Error', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $execution->markFailed($e->getMessage());
            $this->reporter->report($task, $execution);

            return ExecutionResult::failure($e->getMessage());
        }
    }

    private function resolveHandler(string $type): ?TaskHandlerInterface
    {
        return $this->handlers->first(fn (TaskHandlerInterface $h) => $h->canHandle($type));
    }
}