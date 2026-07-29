<?php

namespace App\Jobs;

use App\Services\Swarm\CircuitBreaker;
use App\Services\Swarm\DeadLetterQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteWorkflowStep implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public int $backoff;
    public bool $failOnTimeout = true;

    public function __construct(
        public string $executionId,
        public array $step,
        public array $context = [],
        public int $attempt = 0,
    ) {
        $this->tries = config('swarm.step_retries', 3);
        $this->backoff = [10, 30, 60]; // seconds
    }

    public function handle(): void
    {
        $agentId = $this->step['agent_id'];

        // Check circuit breaker before executing
        if (app(CircuitBreaker::class)->isOpen($agentId)) {
            $this->release(60); // Re-queue for 60s
            return;
        }

        try {
            // ... your existing step execution logic ...
            $this->executeStep();

            // Record success to reset circuit breaker
            app(CircuitBreaker::class)->recordSuccess($agentId);

        } catch (Throwable $e) {
            // Record failure for circuit breaker tracking
            app(CircuitBreaker::class)->recordFailure($agentId);

            throw $e; // Re-throw so Laravel handles retries
        }
    }

    /**
     * Called when all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        app(DeadLetterQueue::class)->record([
            'execution_id' => $this->executionId,
            'step_id' => $this->step['id'] ?? 'unknown',
            'agent_id' => $this->step['agent_id'] ?? 'unknown',
            'error' => $exception,
            'step_config' => $this->step,
            'context' => $this->context,
            'retry_count' => $this->attempts(),
        ]);

        // Optionally broadcast failure
        // broadcast(new \App\Events\StepFailedEvent(...));
    }

    protected function executeStep(): void
    {
        // Your existing step logic
    }
}
