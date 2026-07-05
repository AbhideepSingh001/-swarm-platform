<?php

namespace App\Services\Swarm;

use App\Events\StepCompleted;
use App\Events\StepFailed;
use App\Events\WorkflowFinished;
use App\Events\WorkflowStarted;
use App\Models\SwarmWorkflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use Throwable;

class SwarmRunner
{
    public function __construct(
        private DAGResolver $dagResolver,
        private WorkflowStateMachine $stateMachine,
    ) {}

            public function execute(SwarmWorkflow $workflow, array $context = []): WorkflowExecution
    {
        $execution = $workflow->executions()->create([
            'status' => 'pending',
            'context' => $context,
            'results' => [],
            'checkpoint' => null,
        ]);

        $this->stateMachine->start($execution);
        event(new WorkflowStarted($execution));

        try {
            $this->runWorkflow($execution);
        } catch (Throwable $e) {
            $this->stateMachine->fail($execution);
            event(new WorkflowFinished($execution, 'failed'));
            throw $e;
        }

        return $execution->fresh();
    }

    public function resume(WorkflowExecution $execution): WorkflowExecution
    {
        if (!$execution->isPaused()) {
            throw new \InvalidArgumentException('Execution must be paused to resume');
        }

        $this->stateMachine->resume($execution);

        try {
            $this->runWorkflow($execution);
        } catch (Throwable $e) {
            $this->stateMachine->fail($execution);
            event(new WorkflowFinished($execution, 'failed'));
            throw $e;
        }

        return $execution->fresh();
    }

    private function runWorkflow(WorkflowExecution $execution): void
    {
        $workflow = $execution->workflow;
        $graph = $workflow->toDag();
        $levels = $this->dagResolver->getExecutionLevels($graph);
        $completedSteps = $execution->getCompletedSteps();
        $checkpoint = $execution->checkpoint ?? [];

        foreach ($levels as $levelIndex => $level) {
            $stepsToRun = $level->reject(fn ($name) => in_array($name, $completedSteps, true));

            if ($stepsToRun->isEmpty()) {
                continue;
            }

            $execution->update([
                'checkpoint' => array_merge($checkpoint, ['current_level' => $levelIndex]),
            ]);

            $execution = $execution->fresh();

            if ($execution->isPaused() || $execution->isCancelled()) {
                return;
            }

            $this->executeLevel($execution, $workflow, $stepsToRun->all());
            $execution = $execution->fresh();
        }

        if (!$execution->isTerminal()) {
            $this->stateMachine->complete($execution);
            event(new WorkflowFinished($execution, 'completed'));
        }
    }

    /**
     * @param string[] $stepNames
     */
    private function executeLevel(WorkflowExecution $execution, SwarmWorkflow $workflow, array $stepNames): void
    {
        $steps = $workflow->steps->whereIn('name', $stepNames)->keyBy('name');

        foreach ($stepNames as $stepName) {
            $step = $steps->get($stepName);

            if (!$step) {
                throw new \RuntimeException("Step '{$stepName}' not found in workflow");
            }

            $this->executeStep($execution, $step);

            $execution = $execution->fresh();

            if ($execution->isPaused() || $execution->isCancelled()) {
                return;
            }

            if ($execution->isFailed()) {
                return;
            }
        }
    }

    private function executeStep(WorkflowExecution $execution, WorkflowStep $step, int $attempt = 1): void
    {
        try {
            $result = $this->dispatchStep($execution, $step);

            $execution->appendResult($step->name, [
                'success' => true,
                'output' => $result,
                'attempt' => $attempt,
                'executed_at' => now()->toIso8601String(),
            ]);

            event(new StepCompleted($execution, $step, $result));
        } catch (Throwable $e) {
            if ($attempt <= $step->max_retries) {
                $this->executeStep($execution, $step, $attempt + 1);
                return;
            }

            $execution->appendResult($step->name, [
                'success' => false,
                'error' => $e->getMessage(),
                'attempt' => $attempt,
                'failed_at' => now()->toIso8601String(),
            ]);

            event(new StepFailed($execution, $step, $e));

            $this->stateMachine->fail($execution);
            event(new WorkflowFinished($execution, 'failed'));
        }
    }

    /**
     * Dispatch step to the agent/task system. Override or extend for actual agent integration.
     */
    protected function dispatchStep(WorkflowExecution $execution, WorkflowStep $step): mixed
    {
        // Integration point with Day 16 TaskResult system
        return [
            'agent' => $step->agent,
            'task' => $step->task,
            'context' => $execution->context,
            'step_config' => $step->config,
        ];
    }

    public function pause(WorkflowExecution $execution): void
    {
        $this->stateMachine->pause($execution);
    }

    public function cancel(WorkflowExecution $execution): void
    {
        $this->stateMachine->cancel($execution);
        event(new WorkflowFinished($execution, 'cancelled'));
    }

    public function getStatus(WorkflowExecution $execution): array
    {
        return [
            'id' => $execution->id,
            'status' => $execution->status,
            'workflow' => $execution->workflow->name,
            'started_at' => $execution->started_at,
            'finished_at' => $execution->finished_at,
            'completed_steps' => $execution->getCompletedSteps(),
            'total_steps' => $execution->workflow->steps->count(),
            'progress_percent' => $this->calculateProgress($execution),
        ];
    }

    private function calculateProgress(WorkflowExecution $execution): float
    {
        $total = $execution->workflow->steps->count();
        $completed = count($execution->getCompletedSteps());

        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }
}