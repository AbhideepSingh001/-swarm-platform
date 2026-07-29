<?php

namespace App\Services\Swarm;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkflowResumer
{
    public function resumeFromCheckpoint(string $executionId, string $fromStepId, array $context = []): bool
    {
        $execution = $this->getExecution($executionId);

        if (! $execution) {
            Log::error('Cannot resume workflow: execution not found', ['execution_id' => $executionId]);
            return false;
        }

        $steps = $this->getWorkflowSteps($execution);
        $remainingSteps = $this->getRemainingSteps($steps, $fromStepId);

        if (empty($remainingSteps)) {
            Log::info('No remaining steps to resume', ['execution_id' => $executionId]);
            return true;
        }

        $jobs = collect($remainingSteps)->map(function ($step) use ($executionId, $context) {
            $jobClass = config('swarm.job_class', \App\Jobs\Swarm\ExecuteWorkflowStep::class);
            return new $jobClass(
                executionId: $executionId,
                step: $step,
                context: $context
            );
        });

        Bus::batch($jobs)
            ->then(function ($batch) use ($executionId) {
                Log::info('Resumed workflow batch completed', ['execution_id' => $executionId, 'batch_id' => $batch->id]);
            })
            ->catch(function ($batch, $e) use ($executionId) {
                Log::error('Resumed workflow batch failed', ['execution_id' => $executionId, 'error' => $e->getMessage()]);
            })
            ->dispatch();

        $this->markExecutionResumed($executionId);

        return true;
    }

    public function resumeWithSkippedStep(string $executionId, string $skippedStepId, array $context = []): bool
    {
        $this->markStepSkipped($executionId, $skippedStepId);
        return $this->resumeFromCheckpoint($executionId, $skippedStepId, $context);
    }

    protected function getExecution(string $executionId): ?object
    {
        return DB::table('workflow_executions')
            ->where('id', $executionId)
            ->first();
    }

    protected function getWorkflowSteps(object $execution): array
    {
        // Fetch workflow definition from related swarm_workflows table
        if (!empty($execution->swarm_workflow_id)) {
            $workflow = DB::table('swarm_workflows')->where('id', $execution->swarm_workflow_id)->first();
            if ($workflow && !empty($workflow->definition)) {
                $def = is_string($workflow->definition) ? json_decode($workflow->definition, true) : $workflow->definition;
                return $def['steps'] ?? [];
            }
        }

        return [];
    }

    protected function getRemainingSteps(array $steps, string $fromStepId): array
    {
        $found = false;
        return array_values(array_filter($steps, function ($step) use ($fromStepId, &$found) {
            if (($step['id'] ?? null) === $fromStepId) {
                $found = true;
                return false;
            }
            return $found;
        }));
    }

    protected function markExecutionResumed(string $executionId): void
    {
        DB::table('workflow_executions')
            ->where('id', $executionId)
            ->update([
                'status' => 'resumed',
                'updated_at' => now(),
            ]);
    }

    protected function markStepSkipped(string $executionId, string $stepId): void
    {
        try {
            $execution = DB::table('workflow_executions')->where('id', $executionId)->first();

            $skipped = [];
            if ($execution && !empty($execution->skipped_steps)) {
                $skipped = json_decode($execution->skipped_steps, true) ?? [];
            }

            $skipped[] = [
                'step_id' => $stepId,
                'skipped_at' => now()->toDateTimeString(),
            ];

            DB::table('workflow_executions')
                ->where('id', $executionId)
                ->update([
                    'skipped_steps' => json_encode($skipped),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Could not mark step as skipped', [
                'execution_id' => $executionId,
                'step_id' => $stepId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
