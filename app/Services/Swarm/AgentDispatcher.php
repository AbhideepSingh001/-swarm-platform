<?php

namespace App\Services\Swarm;

use App\Models\WorkflowExecution;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class AgentDispatcher
{
    public function isWired(): bool
    {
        return false;
    }

    /**
     * Dispatch a step or an entire workflow execution.
     *
     * @param array|WorkflowExecution $target
     */
    public function dispatch(array|WorkflowExecution $target, array $context = [], bool $mockMode = false): array|string
    {
        if ($target instanceof WorkflowExecution) {
            return $this->dispatchExecution($target);
        }

        // Original array-based step dispatch
        $stepId = $target['id'] ?? 'unknown';
        $agentId = $target['agent_id'] ?? $target['agent'] ?? 'mock-agent';

        if ($mockMode) {
            return [
                'step_id' => $stepId,
                'agent_id' => $agentId,
                'status' => 'completed',
                'output' => [
                    'result' => "Mock result for step {$stepId} via agent {$agentId}",
                    'context_keys' => array_keys($context),
                    'timestamp' => now()->toIso8601String(),
                ],
                'metadata' => [
                    'mock' => true,
                ],
                'timestamp' => now()->toIso8601String(),
                'context' => $context,
            ];
        }

        throw new \RuntimeException('Day 16 agent system not wired. Enable mock mode for testing.');
    }

    protected function dispatchExecution(WorkflowExecution $execution): string
    {
        $workflow = $execution->workflow;

        if (! $workflow) {
            throw new \RuntimeException('Execution has no associated workflow');
        }

        $steps = $this->stepsForWorkflow($workflow);

        if (empty($steps)) {
            throw new \RuntimeException('Workflow has no steps');
        }

        $jobs = collect($steps)->map(function ($step) use ($execution) {
            $jobClass = config('swarm.job_class', \App\Jobs\Swarm\ExecuteWorkflowStep::class);
            return new $jobClass(
                executionId: (string) $execution->id,
                step: $step,
                context: $execution->context ?? []
            );
        });

        $batch = Bus::batch($jobs)
            ->then(function ($batch) use ($execution) {
                $execution->update(['status' => 'completed', 'finished_at' => now()]);
                Log::info('Workflow batch completed', ['execution_id' => $execution->id]);
            })
            ->catch(function ($batch, $e) use ($execution) {
                $execution->update(['status' => 'failed', 'finished_at' => now()]);
                Log::error('Workflow batch failed', ['execution_id' => $execution->id, 'error' => $e->getMessage()]);
            })
            ->dispatch();

        $execution->update([
            'status' => 'running',
            'started_at' => now(),
            'checkpoint' => array_merge($execution->checkpoint ?? [], ['batch_id' => $batch->id]),
        ]);

        return $batch->id;
    }

    private function stepsForWorkflow(object $workflow): array
    {
        $definition = $workflow->definition ?? null;
        $definition = is_string($definition)
            ? json_decode($definition, true)
            : $definition;

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
}
