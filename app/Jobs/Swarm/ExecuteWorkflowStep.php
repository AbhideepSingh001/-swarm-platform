<?php

declare(strict_types=1);

namespace App\Jobs\Swarm;

use App\Services\Swarm\AgentDispatcher;
use App\Services\Swarm\StepRetryDecorator;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteWorkflowStep implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly string $executionId,
        public readonly array $step,
        public readonly array $context = [],
        public readonly int $levelIndex = 0,
        public readonly int $stepIndex = 0,
    ) {}

    public function handle(
        StepRetryDecorator $retryDecorator,
        AgentDispatcher $agentDispatcher,
    ): void {
        $stepId = $this->step['id'] ?? 'unknown';
        $stepName = $this->step['name'] ?? $stepId;

        Log::info('ExecuteWorkflowStep: starting', [
            'execution_id' => $this->executionId,
            'step_id' => $stepId,
            'step_name' => $stepName,
            'level' => $this->levelIndex,
            'index' => $this->stepIndex,
            'queue' => $this->queue,
        ]);

        $this->broadcast('step.started', [
            'execution_id' => $this->executionId,
            'step_id' => $stepId,
            'step_name' => $stepName,
            'level' => $this->levelIndex,
            'index' => $this->stepIndex,
        ]);

        $result = $retryDecorator->call(
            function () use ($agentDispatcher, $stepId) {
                return $agentDispatcher->dispatch(
                    $this->step,
                    array_merge($this->context, [
                        '_execution_id' => $this->executionId,
                        '_step_id' => $stepId,
                    ]),
                    mockMode: ! $agentDispatcher->isWired(),
                );
            },
            maxAttempts: $this->step['retry']['max_attempts'] ?? 3,
            baseDelayMs: $this->step['retry']['base_delay_ms'] ?? 1000,
            backoffMultiplier: $this->step['retry']['backoff_multiplier'] ?? 2.0,
            useJitter: $this->step['retry']['use_jitter'] ?? true,
            retryOnly: $this->step['retry']['retry_only'] ?? [],
        );

        Log::info('ExecuteWorkflowStep: completed', [
            'execution_id' => $this->executionId,
            'step_id' => $stepId,
            'status' => 'success',
        ]);

        $this->broadcast('step.completed', [
            'execution_id' => $this->executionId,
            'step_id' => $stepId,
            'step_name' => $stepName,
            'level' => $this->levelIndex,
            'index' => $this->stepIndex,
            'result' => $result,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $stepId = $this->step['id'] ?? 'unknown';

        Log::error('ExecuteWorkflowStep: failed permanently', [
            'execution_id' => $this->executionId,
            'step_id' => $stepId,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);

        $this->broadcast('step.failed', [
            'execution_id' => $this->executionId,
            'step_id' => $stepId,
            'step_name' => $this->step['name'] ?? $stepId,
            'level' => $this->levelIndex,
            'index' => $this->stepIndex,
            'error' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
            ],
        ]);
    }

    private function broadcast(string $event, array $payload): void
    {
        try {
            broadcast(new \App\Events\Swarm\StepEvent($event, $payload));
        } catch (Throwable $e) {
            Log::debug('ExecuteWorkflowStep: broadcast skipped', [
                'event' => $event,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
