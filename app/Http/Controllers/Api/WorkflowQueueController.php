<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Swarm\AsyncSwarmRunner;
use App\Models\WorkflowExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowQueueController
{
    public function __construct(
        private readonly AsyncSwarmRunner $swarmRunner,
    ) {}

    public function dispatch(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'input' => ['sometimes', 'array'],
            'steps' => ['sometimes', 'array'],
            'edges' => ['sometimes', 'array'],
        ]);

        $workflow = [
            'name' => $validated['name'] ?? 'Workflow ' . $id,
            'steps' => $validated['steps'] ?? [],
            'edges' => $validated['edges'] ?? [],
        ];

        try {
            $result = $this->swarmRunner->dispatch($id, $workflow, $validated['input'] ?? []);

            return response()->json([
                'execution_id' => $result['execution_id'],
                'status' => $result['status'],
                'batch_id' => $result['batch_id'],
                'message' => 'Workflow queued successfully',
            ], 202);
        } catch (Throwable $e) {
            Log::error('WorkflowQueueController: dispatch failed', [
                'workflow_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to dispatch workflow',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function poll(string $id): JsonResponse
    {
        try {
            $execution = \App\Models\WorkflowExecution::find($id);

            if (! $execution) {
                return response()->json([
                    'error' => 'Execution not found',
                ], 404);
            }

            $meta = $execution->context ?? [];
            $batchProgress = null;

            if (! empty($execution->batch_id)) {
                try {
                    $batch = Bus::findBatch($execution->batch_id);
                    if ($batch) {
                        $batchProgress = [
                            'processed' => $batch->processedJobs(),
                            'total' => $batch->totalJobs,
                            'pending' => $batch->pendingJobs,
                            'failed' => $batch->failedJobs,
                            'finished' => $batch->finished(),
                            'cancelled' => $batch->cancelled(),
                        ];
                    }
                } catch (Throwable) {
                }
            }

            return response()->json([
                'execution_id' => $id,
                'status' => $execution->status,
                'progress' => [
                    'percent' => $meta['progress_percent'] ?? 0,
                    'completed_steps' => $meta['completed_steps'] ?? 0,
                    'total_steps' => $meta['total_steps'] ?? 0,
                    'current_level' => $meta['current_level'] ?? 0,
                    'total_levels' => $meta['total_levels'] ?? 0,
                ],
                'batch' => $batchProgress,
                'meta' => $meta,
                'created_at' => $execution->created_at?->toIso8601String(),
                'updated_at' => $execution->updated_at?->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            Log::error('WorkflowQueueController: poll failed', [
                'execution_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to poll execution',
            ], 500);
        }
    }

    public function status(string $id): JsonResponse
    {
        $execution = WorkflowExecution::find($id);

        if (! $execution) {
            return response()->json(['message' => 'Execution not found'], 404);
        }

        return response()->json([
            'execution_id' => $execution->id,
            'workflow_id' => $execution->swarm_workflow_id,
            'status' => $execution->status,
            'results' => $execution->results ?? [],
            'checkpoint' => $execution->checkpoint ?? [],
        ]);
    }

    public function cancel(string $id): JsonResponse
    {
        $execution = WorkflowExecution::find($id);

        if (! $execution) {
            return response()->json(['message' => 'Execution not found'], 404);
        }

        if ($execution->isTerminal()) {
            return response()->json(['message' => 'Workflow is already terminal'], 422);
        }

        $this->swarmRunner->cancel($execution);

        return response()->json([
            'execution_id' => $execution->id,
            'status' => $execution->fresh()->status,
        ]);
    }

    public function retry(Request $request, string $id): JsonResponse
    {
        try {
            $execution = \App\Models\WorkflowExecution::find($id);

            if (! $execution) {
                return response()->json(['error' => 'Execution not found'], 404);
            }

            if (! in_array($execution->status, ['failed', 'cancelled'], true)) {
                return response()->json([
                    'error' => 'Only failed or cancelled executions can be retried',
                    'current_status' => $execution->status,
                ], 422);
            }

            if (! empty($execution->batch_id)) {
                try {
                    $oldBatch = Bus::findBatch($execution->batch_id);
                    $oldBatch?->cancel();
                } catch (Throwable) {
                }
            }

            $workflow = $execution->context['workflow_snapshot'] ?? [
                'steps' => [],
                'edges' => [],
            ];

            $result = $this->swarmRunner->dispatch(
                $execution->swarm_workflow_id,
                $workflow,
                $execution->context['input'] ?? []
            );

            return response()->json([
                'execution_id' => $result['execution_id'],
                'status' => $result['status'],
                'batch_id' => $result['batch_id'],
                'message' => 'Execution retried successfully',
            ], 202);
        } catch (Throwable $e) {
            Log::error('WorkflowQueueController: retry failed', [
                'execution_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to retry execution',
            ], 500);
        }
    }

    public function metrics(string $id): JsonResponse
    {
        try {
            $execution = \App\Models\WorkflowExecution::find($id);

            if (! $execution) {
                return response()->json(['error' => 'Execution not found'], 404);
            }

            $meta = $execution->context ?? [];
            $completedAt = $execution->updated_at;
            $createdAt = $execution->created_at;

            $duration = null;
            if ($completedAt && $createdAt) {
                $duration = $completedAt->diffInSeconds($createdAt);
            }

            return response()->json([
                'execution_id' => $id,
                'workflow_id' => $execution->swarm_workflow_id,
                'status' => $execution->status,
                'duration_seconds' => $duration,
                'step_times' => $meta['step_times'] ?? [],
                'retry_count' => $meta['retry_count'] ?? 0,
                'levels_completed' => $meta['completed_levels'] ?? 0,
                'total_levels' => $meta['total_levels'] ?? 0,
                'peak_parallel_jobs' => $meta['peak_parallel_jobs'] ?? 0,
                'created_at' => $createdAt?->toIso8601String(),
                'completed_at' => $meta['completed_at'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('WorkflowQueueController: metrics failed', [
                'execution_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch metrics',
            ], 500);
        }
    }
}
