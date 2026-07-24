<?php

declare(strict_types=1);

namespace App\Services\Swarm;

use App\Jobs\Swarm\ExecuteWorkflowStep;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowBatchMonitor
{
    public function __construct(
        private readonly ?BatchBroadcastService $broadcaster = null,
    ) {}

    public function onLevelComplete(Batch $batch): void
    {
        $executionId = $batch->options['execution_id'] ?? 'unknown';
        $levelIndex = $batch->options['level_index'] ?? 0;
        $totalLevels = $batch->options['total_levels'] ?? 1;
        $nextLevelSteps = $batch->options['next_level_steps'] ?? [];
        $context = $batch->options['context'] ?? [];

        Log::info('WorkflowBatchMonitor: level completed', [
            'execution_id' => $executionId,
            'level' => $levelIndex,
            'total_levels' => $totalLevels,
            'has_next_level' => ! empty($nextLevelSteps),
        ]);

        $this->updateExecutionStatus($executionId, 'running', [
            'current_level' => $levelIndex,
            'completed_levels' => $levelIndex + 1,
            'total_levels' => $totalLevels,
            'progress_percent' => (int) ((($levelIndex + 1) / $totalLevels) * 100),
        ]);

        $this->broadcast('level.completed', [
            'execution_id' => $executionId,
            'level' => $levelIndex,
            'total_levels' => $totalLevels,
            'progress_percent' => (int) ((($levelIndex + 1) / $totalLevels) * 100),
        ]);

        if (! empty($nextLevelSteps)) {
            $this->dispatchNextLevel($executionId, $levelIndex + 1, $nextLevelSteps, $context, $totalLevels);
        } else {
            $this->onWorkflowComplete($batch);
        }
    }

    public function onLevelFailure(Batch $batch, Throwable $exception): void
    {
        $executionId = $batch->options['execution_id'] ?? 'unknown';
        $levelIndex = $batch->options['level_index'] ?? 0;

        Log::error('WorkflowBatchMonitor: level failed', [
            'execution_id' => $executionId,
            'level' => $levelIndex,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);

        $this->updateExecutionStatus($executionId, 'failed', [
            'failed_at_level' => $levelIndex,
            'error' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
            ],
        ]);

        $this->broadcast('level.failed', [
            'execution_id' => $executionId,
            'level' => $levelIndex,
            'error' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
            ],
        ]);

        $this->cancelRemainingBatches($executionId);
    }

    public function onWorkflowComplete(Batch $batch): void
    {
        $executionId = $batch->options['execution_id'] ?? 'unknown';
        $totalLevels = $batch->options['total_levels'] ?? 1;

        Log::info('WorkflowBatchMonitor: workflow complete', [
            'execution_id' => $executionId,
            'total_levels' => $totalLevels,
        ]);

        $this->updateExecutionStatus($executionId, 'completed', [
            'completed_at' => now()->toIso8601String(),
            'progress_percent' => 100,
        ]);

        $this->broadcast('workflow.completed', [
            'execution_id' => $executionId,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);
    }

    protected function dispatchNextLevel(
        string $executionId,
        int $nextLevelIndex,
        array $steps,
        array $context,
        int $totalLevels,
    ): void {
        Log::info('WorkflowBatchMonitor: dispatching next level', [
            'execution_id' => $executionId,
            'next_level' => $nextLevelIndex,
            'step_count' => count($steps),
        ]);

        $jobs = [];
        foreach ($steps as $index => $step) {
            $jobs[] = new ExecuteWorkflowStep(
                executionId: $executionId,
                step: $step,
                context: $context,
                levelIndex: $nextLevelIndex,
                stepIndex: $index,
            );
        }

        $nextLevelSteps = $context['_levels'][$nextLevelIndex + 1] ?? [];
        $batchMonitor = $this;

        Bus::batch($jobs)
            ->name("swarm-{$executionId}-level-{$nextLevelIndex}")
            ->onQueue('swarm-steps')
            ->then(function (Batch $batch) use ($batchMonitor, $executionId, $nextLevelIndex, $totalLevels, $nextLevelSteps, $context) {
                $batch->options['execution_id'] = $executionId;
                $batch->options['level_index'] = $nextLevelIndex;
                $batch->options['total_levels'] = $totalLevels;
                $batch->options['next_level_steps'] = $nextLevelSteps;
                $batch->options['context'] = $context;
                $batchMonitor->onLevelComplete($batch);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($batchMonitor, $executionId, $nextLevelIndex) {
                $batch->options['execution_id'] = $executionId;
                $batch->options['level_index'] = $nextLevelIndex;
                $batchMonitor->onLevelFailure($batch, $e);
            })
            ->finally(function (Batch $batch) use ($batchMonitor, $executionId, $totalLevels) {
                $batch->options['execution_id'] = $executionId;
                $batch->options['total_levels'] = $totalLevels;
                $batchMonitor->onWorkflowComplete($batch);
            })
            ->dispatch();
    }

    protected function updateExecutionStatus(string $executionId, string $status, array $data = []): void
    {
        try {
            $execution = \App\Models\WorkflowExecution::find($executionId);
            if ($execution) {
                $updateData = ['status' => $status];

                if (! empty($data)) {
                    $updateData['context'] = array_merge($execution->context ?? [], $data);
                }

                $execution->update($updateData);
            }
        } catch (Throwable $e) {
            Log::warning('WorkflowBatchMonitor: could not update execution', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function broadcast(string $event, array $payload): void
    {
        try {
            if ($this->broadcaster) {
                // Use broadcaster if available
            }
            \Illuminate\Support\Facades\Broadcast::event(
                new \App\Events\Swarm\WorkflowEvent($event, $payload)
            );
        } catch (Throwable $e) {
            Log::debug('WorkflowBatchMonitor: broadcast skipped', [
                'event' => $event,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    protected function cancelRemainingBatches(string $executionId): void
    {
        Log::info('WorkflowBatchMonitor: cancelled remaining batches', [
            'execution_id' => $executionId,
        ]);
    }
}
