<?php

namespace App\Services\Swarm;

use App\Models\DeadLetter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecoveryOrchestrator
{
    public function __construct(
        protected DeadLetterQueue $deadLetterQueue,
        protected CircuitBreaker $circuitBreaker,
        protected WorkflowResumer $workflowResumer,
    ) {}

    public function retry(DeadLetter $deadLetter, array $overrides = []): array
    {
        if (! $deadLetter->isOpen()) {
            return ['success' => false, 'message' => 'Dead letter is not in open status.'];
        }

        $agentId = $overrides['agent_id'] ?? $deadLetter->agent_id;

        if ($this->circuitBreaker->isOpen($agentId)) {
            return [
                'success' => false,
                'message' => "Circuit breaker is open for agent {$agentId}. Try again later or reassign.",
            ];
        }

        $deadLetter->markRetrying();

        try {
            $job = $this->buildJob($deadLetter, $overrides);

            if ($job === null) {
                $deadLetter->update(['status' => 'open']);
                return ['success' => false, 'message' => 'Could not construct retry job. Check job class configuration.'];
            }

            Bus::dispatch($job);

            $deadLetter->markResolved('retried');

            Log::info('Dead letter retry dispatched', [
                'dead_letter_id' => $deadLetter->id,
                'execution_id' => $deadLetter->execution_id,
                'agent_id' => $agentId,
            ]);

            return ['success' => true, 'message' => 'Retry dispatched successfully.'];
        } catch (Throwable $e) {
            $deadLetter->update(['status' => 'open']);

            Log::error('Dead letter retry failed to dispatch', [
                'dead_letter_id' => $deadLetter->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed to dispatch retry: ' . $e->getMessage()];
        }
    }

    public function skip(DeadLetter $deadLetter, string $reason = null): array
    {
        if (! $deadLetter->isOpen()) {
            return ['success' => false, 'message' => 'Dead letter is not in open status.'];
        }

        $deadLetter->markResolved($reason ?? 'skipped');

        $this->workflowResumer->resumeWithSkippedStep(
            $deadLetter->execution_id,
            $deadLetter->step_id,
            $deadLetter->context
        );

        Log::info('Dead letter skipped, workflow resumed', [
            'dead_letter_id' => $deadLetter->id,
            'execution_id' => $deadLetter->execution_id,
        ]);

        return ['success' => true, 'message' => 'Step skipped, workflow resumed.'];
    }

    public function reassign(DeadLetter $deadLetter, string $newAgentId, array $overrides = []): array
    {
        if (! $deadLetter->isOpen()) {
            return ['success' => false, 'message' => 'Dead letter is not in open status.'];
        }

        if ($this->circuitBreaker->isOpen($newAgentId)) {
            return [
                'success' => false,
                'message' => "Circuit breaker is open for agent {$newAgentId}. Choose a different agent.",
            ];
        }

        $overrides['agent_id'] = $newAgentId;

        return $this->retry($deadLetter, $overrides);
    }

    protected function buildJob(DeadLetter $deadLetter, array $overrides): ?object
    {
        $jobClass = config('swarm.job_class', \App\Jobs\ExecuteWorkflowStep::class);

        if (! class_exists($jobClass)) {
            return null;
        }

        $step = array_merge($deadLetter->step_config, ['agent_id' => $overrides['agent_id'] ?? $deadLetter->agent_id]);
        $context = array_merge($deadLetter->context, $overrides['context'] ?? []);
        $attempt = ($deadLetter->retry_count + 1);

        // Try common constructor patterns
        try {
            $reflector = new \ReflectionClass($jobClass);
            $constructor = $reflector->getConstructor();

            if (! $constructor) {
                return $reflector->newInstance();
            }

            $params = $constructor->getParameters();
            $args = [];

            foreach ($params as $param) {
                $name = $param->getName();
                $type = $param->getType();

                if ($name === 'executionId' || $name === 'execution_id') {
                    $args[] = $deadLetter->execution_id;
                } elseif ($name === 'step') {
                    $args[] = $step;
                } elseif ($name === 'context') {
                    $args[] = $context;
                } elseif ($name === 'attempt') {
                    $args[] = $attempt;
                } elseif ($type && ! $type->isBuiltin() && $type->getName() === 'array') {
                    $args[] = [];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    $args[] = null;
                }
            }

            return $reflector->newInstanceArgs($args);
        } catch (\Throwable $e) {
            Log::warning('Failed to build job via reflection, falling back to simple dispatch', [
                'job_class' => $jobClass,
                'error' => $e->getMessage(),
            ]);

            // Fallback: try named arguments for common patterns
            try {
                return new $jobClass(
                    executionId: $deadLetter->execution_id,
                    step: $step,
                    context: $context,
                    attempt: $attempt
                );
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }
}
