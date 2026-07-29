<?php

namespace App\Services\Swarm;

use App\Models\SwarmWorkflow;
use App\Models\WorkflowExecution;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AsyncSwarmRunner
{
    public function __construct(
        protected DAGResolver $dagResolver,
        protected WorkflowBatchMonitor $batchMonitor,
    ) {}

    public function run(SwarmWorkflow $workflow, array $context = []): WorkflowExecution
    {
        $execution = WorkflowExecution::create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'pending',
            'context' => $context,
        ]);

        $levels = $this->resolveLevels($workflow);

        if (empty($levels)) {
            $execution->update(['status' => 'completed', 'finished_at' => now()]);
            return $execution;
        }

        $this->dispatchLevel($execution, $levels, 0);

        return $execution;
    }

    public function dispatch(string $workflowId, array $workflowDefinition, array $input = []): array
    {
        $workflow = SwarmWorkflow::find($workflowId);

        if (! $workflow) {
            $workflow = SwarmWorkflow::create([
                'name' => $workflowDefinition['name'] ?? "Workflow {$workflowId}",
                'definition' => $workflowDefinition,
                'config' => [],
                'is_active' => true,
            ]);
        } elseif (! empty($workflowDefinition)) {
            $workflow->update(['definition' => $workflowDefinition]);
        }

        $execution = $this->run($workflow, array_merge($input, [
            'workflow_snapshot' => $workflowDefinition,
            'input' => $input,
        ]));

        $execution->refresh();

        return [
            'execution_id' => (string) $execution->id,
            'status' => $execution->status,
            'batch_id' => $execution->batch_id ?? ($execution->checkpoint['batch_id'] ?? null),
        ];
    }

    public function resolveLevels(SwarmWorkflow $workflow): array
    {
        $steps = $this->stepsForWorkflow($workflow);
        $graph = [];

        foreach ($steps as $step) {
            $graph[$step['id'] ?? $step['name']] = $step['depends_on'] ?? [];
        }

        return $this->dagResolver
            ->getExecutionLevels($graph)
            ->map(fn ($level) => $level
                ->map(fn ($stepId) => $this->findStepById($steps, $stepId))
                ->values()
                ->all())
            ->values()
            ->all();
    }

    public function cancel(WorkflowExecution $execution): bool
    {
        if (in_array($execution->status, ['completed', 'failed', 'cancelled'])) {
            return false;
        }

        $batchId = $execution->batch_id ?? ($execution->checkpoint['batch_id'] ?? null);

        if (!empty($batchId)) {
            try {
                Bus::findBatch($batchId)?->cancel();
            } catch (\Throwable $e) {
                Log::warning('Failed to cancel batch', ['batch_id' => $batchId, 'error' => $e->getMessage()]);
            }
        }

        $execution->update([
            'status' => 'cancelled',
            'finished_at' => now(),
        ]);

        return true;
    }

    protected function dispatchLevel(WorkflowExecution $execution, array $levels, int $levelIndex): void
    {
        if (!isset($levels[$levelIndex])) {
            $execution->update(['status' => 'completed', 'finished_at' => now()]);
            return;
        }

        $steps = $levels[$levelIndex];
        $jobs = collect($steps)->map(function ($step) use ($execution) {
            $jobClass = config('swarm.job_class', \App\Jobs\Swarm\ExecuteWorkflowStep::class);
            return new $jobClass(
                executionId: (string) $execution->id,
                step: $step,
                context: $execution->context ?? []
            );
        });

        $batch = Bus::batch($jobs)
            ->then(function ($batch) use ($execution, $levels, $levelIndex) {
                $this->dispatchLevel($execution, $levels, $levelIndex + 1);
            })
            ->catch(function ($batch, $e) use ($execution) {
                $execution->update(['status' => 'failed', 'finished_at' => now()]);
                Log::error('Level batch failed', ['execution_id' => $execution->id, 'error' => $e->getMessage()]);
            })
            ->dispatch();

        $this->batchMonitor->track($execution, $batch->id);

        if ($levelIndex === 0) {
            $execution->update([
                'status' => 'running',
                'started_at' => now(),
                'batch_id' => $batch->id,
                'checkpoint' => array_merge($execution->checkpoint ?? [], ['batch_id' => $batch->id]),
            ]);
        }
    }

    private function stepsForWorkflow(SwarmWorkflow $workflow): array
    {
        $definition = is_string($workflow->definition)
            ? json_decode($workflow->definition, true)
            : $workflow->definition;

        if (! empty($definition['steps'])) {
            return $definition['steps'];
        }

        return $workflow->steps()
            ->get()
            ->map(fn ($step) => [
                'id' => $step->name,
                'name' => $step->name,
                'agent_id' => $step->agent,
                'task' => $step->task,
                'config' => $step->config ?? [],
                'depends_on' => $step->depends_on ?? [],
                'retry' => [
                    'max_attempts' => max(1, ($step->max_retries ?? 0) + 1),
                ],
            ])
            ->all();
    }

    private function findStepById(array $steps, string $stepId): array
    {
        foreach ($steps as $step) {
            if (($step['id'] ?? $step['name'] ?? null) === $stepId) {
                return $step;
            }
        }

        throw new RuntimeException("Step {$stepId} was not found in workflow");
    }
}
