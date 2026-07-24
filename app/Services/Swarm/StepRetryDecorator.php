<?php

declare(strict_types=1);

namespace App\Services\Swarm;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

class StepRetryDecorator
{
    public function call(
        Closure $step,
        int $maxAttempts = 3,
        int $baseDelayMs = 1000,
        float $backoffMultiplier = 2.0,
        bool $useJitter = true,
        array $retryOnly = []
    ): mixed {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                Log::debug('StepRetryDecorator: executing step', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);

                return $step();
            } catch (Throwable $e) {
                $lastException = $e;

                if (! empty($retryOnly) && ! $this->shouldRetry($e, $retryOnly)) {
                    Log::warning('StepRetryDecorator: non-retryable exception, failing fast', [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                if ($attempt === $maxAttempts) {
                    break;
                }

                $delayMs = $this->calculateDelay($attempt, $baseDelayMs, $backoffMultiplier, $useJitter);

                Log::warning('StepRetryDecorator: attempt failed, retrying', [
                    'attempt' => $attempt,
                    'next_attempt' => $attempt + 1,
                    'delay_ms' => $delayMs,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);

                Sleep::for($delayMs)->milliseconds();
            }
        }

        Log::error('StepRetryDecorator: all attempts exhausted', [
            'max_attempts' => $maxAttempts,
            'exception' => get_class($lastException),
            'message' => $lastException?->getMessage(),
        ]);

        throw $lastException;
    }

    private function shouldRetry(Throwable $exception, array $retryOnly): bool
    {
        foreach ($retryOnly as $retryableClass) {
            if ($exception instanceof $retryableClass) {
                return true;
            }
        }
        return false;
    }

    private function calculateDelay(
        int $attempt,
        int $baseDelayMs,
        float $backoffMultiplier,
        bool $useJitter
    ): int {
        $delay = (int) ($baseDelayMs * pow($backoffMultiplier, $attempt - 1));

        if ($useJitter) {
            $jitter = (int) ($delay * 0.25);
            $delay += random_int(-$jitter, $jitter);
        }

        return min($delay, 30000);
    }
}
