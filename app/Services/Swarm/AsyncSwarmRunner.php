<?php

declare(strict_types=1);

namespace App\Services\Swarm;

use App\Jobs\Swarm\ExecuteWorkflowStep;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class AsyncSwarmRunner
{
    public function __construct(
        private readonly WorkflowBatchMonitor $batchMonitor,
    ) {}

    public function dispatch(string $workflowId, array $workflow, array $input = []): array
    {
        $execution = $this->createExecution($workflowId, $workflow, $input);
        $executionId = (string) $execution['id'];

        Log::info('AsyncSwarmRunner: dispatching workflow', [
            'execution_id' => $executionId,
            'workflow_id' => $workflowId,
            'step_count' => count($workflow['steps'] ?? []),
        ]);

        $levels = $this->resolveDagLevels($workflow);

        if (empty($levels)) {
            $this->updateExecutionStatus($executionId, 'completed', [
                'progress_percent' => 100,
                'message' => 'No steps to execute',
            ]);

            return [
                'execution_id' => $executionId,
                'status' => 'completed',
                'batch_id' => null,
            ];
        }

        $context = array_merge($input, [
            '_workflow_id' => $workflowId,
            '_execution_id' => $executionId,
            '_levels' => $levels,
        ]);

        $firstLevelSteps = $levels[0];
        $nextLevelSteps = $levels[1] ?? [];
        $totalLevels = count($levels);

        $jobs = [];
        foreach ($firstLevelSteps as $index => $step) {
            $jobs[] = new ExecuteWorkflowStep(
                executionId: $executionId,
                step: $step,
                context: $context,
                levelIndex: 0,
                stepIndex: $index,
            );
        }

        $this->updateExecutionStatus($executionId, 'queued', [
            'total_levels' => $totalLevels,
            'total_steps' => array_sum(array_map('count', $levels)),
            '_context' => $context,
        ]);

        $batchMonitor = $this->batchMonitor;

        $batch = Bus::batch($jobs)
            ->name("swarm-{$executionId}-level-0")
            ->onQueue('swarm-steps')
            ->then(function (Batch $batch) use ($batchMonitor, $executionId, $totalLevels, $nextLevelSteps, $context) {
                $batch->options['execution_id'] = $executionId;
                $batch->options['level_index'] = 0;
                $batch->options['total_levels'] = $totalLevels;
                $batch->options['next_level_steps'] = $nextLevelSteps;
                $batch->options['context'] = $context;
                $batchMonitor->onLevelComplete($batch);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($batchMonitor, $executionId) {
                $batch->options['execution_id'] = $executionId;
                $batch->options['level_index'] = 0;
                $batchMonitor->onLevelFailure($batch, $e);
            })
            ->finally(function (Batch $batch) use ($batchMonitor, $executionId, $totalLevels) {
                $batch->options['execution_id'] = $executionId;
                $batch->options['total_levels'] = $totalLevels;
                $batchMonitor->onWorkflowComplete($batch);
            })
            ->dispatch();

        $this->updateExecutionStatus($executionId, 'queued', [
            'batch_id' => $batch->id,
        ]);

        return [
            'execution_id' => $executionId,
            'status' => 'queued',
            'batch_id' => $batch->id,
        ];
    }

    public function resolveDagLevels(array $workflow): array
    {
        $steps = $workflow['steps'] ?? [];
        $edges = $workflow['edges'] ?? [];

        if (empty($steps)) {
            return [];
        }

        $inDegree = [];
        $dependents = [];

        foreach ($steps as $step) {
            $stepId = $step['id'];
            $inDegree[$stepId] = 0;
            $dependents[$stepId] = [];
        }

        foreach ($edges as $edge) {
            $from = $edge['from'] ?? $edge['source'] ?? null;
            $to = $edge['to'] ?? $edge['target'] ?? null;

            if ($from && $to && isset($inDegree[$to])) {
                $inDegree[$to]++;
                $dependents[$from][] = $to;
            }
        }

        $levels = [];
        $assigned = [];
        $queue = [];

        foreach ($steps as $step) {
            if ($inDegree[$step['id']] === 0) {
                $queue[] = $step['id'];
                $assigned[$step['id']] = 0;
            }
        }

        while (! empty($queue)) {
            $currentId = array_shift($queue);
            $currentLevel = $assigned[$currentId];

            if (! isset($levels[$currentLevel])) {
                $levels[$currentLevel] = [];
            }

            foreach ($steps as $step) {
                if ($step['id'] === $currentId) {
                    $levels[$currentLevel][] = $step;
                    break;
                }
            }

            foreach ($dependents[$currentId] as $dependentId) {
                $inDegree[$dependentId]--;
                $newLevel = $currentLevel + 1;
                $assigned[$dependentId] = max($assigned[$dependentId] ?? 0, $newLevel);

                if ($inDegree[$dependentId] === 0) {
                    $queue[] = $dependentId;
                }
            }
        }

        $processedCount = array_sum(array_map('count', $levels));
        if ($processedCount !== count($steps)) {
            $unprocessed = array_diff(
                array_column($steps, 'id'),
                array_keys($assigned)
            );

            Log::error('AsyncSwarmRunner: cycle detected in workflow DAG', [
                'unprocessed_steps' => $unprocessed,
            ]);

            throw new \RuntimeException(
                'Cycle detected in workflow DAG. Unprocessed steps: ' . implode(', ', $unprocessed)
            );
        }

        return array_values($levels);
    }

    protected function createExecution(string $workflowId, array $workflow, array $input): array
    {
        try {
            $execution = \App\Models\WorkflowExecution::create([
                'swarm_workflow_id' => $workflowId,
                'status' => 'pending',
                'context' => array_merge($input, [
                    'workflow_name' => $workflow['name'] ?? 'Untitled',
                    'step_count' => count($workflow['steps'] ?? []),
                ]),
            ]);

            return [
                'id' => (string) $execution->id,
                'swarm_workflow_id' => $workflowId,
                'status' => 'pending',
            ];
        } catch (Throwable $e) {
            Log::error('AsyncSwarmRunner: failed to create execution', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage(),
            ]);

            $tempId = 'exec-' . uniqid();

            return [
                'id' => $tempId,
                'swarm_workflow_id' => $workflowId,
                'status' => 'pending',
            ];
        }
    }

    protected function updateExecutionStatus(string $executionId, string $status, array $data = []): void
    {
        try {
            $execution = \App\Models\WorkflowExecution::find($executionId);

            if ($execution) {
                $updateData = ['status' => $status];

                if (isset($data['batch_id'])) {
                    $updateData['batch_id'] = $data['batch_id'];
                    unset($data['batch_id']);
                }

                if (! empty($data)) {
                    $updateData['context'] = array_merge($execution->context ?? [], $data);
                }

                $execution->update($updateData);
            }
        } catch (Throwable $e) {
            Log::warning('AsyncSwarmRunner: could not update execution', [
                'execution_id' => $executionId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
