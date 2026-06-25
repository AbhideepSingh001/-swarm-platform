<?php

declare(strict_types=1);

namespace App\Execution;

use App\Events\TaskRetrying;
use App\Models\Task;
use App\ValueObjects\ExecutionResult;
use Illuminate\Support\Facades\Log;

class RetryManager
{
    private int $baseDelay;
    private float $backoffMultiplier;
    private int $maxDelay;

    public function __construct(array $config = [])
    {
        $this->baseDelay = $config['base_delay'] ?? 2;
        $this->backoffMultiplier = $config['backoff_multiplier'] ?? 2.0;
        $this->maxDelay = $config['max_delay'] ?? 300;
    }

    /**
     * Execute a callback with retry logic.
     *
     * @param callable(): ExecutionResult $callback
     */
    public function executeWithRetry(
        Task $task,
        callable $callback,
        int $maxAttempts,
        array $retryableErrors = []
    ): ExecutionResult {
        $lastResult = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $result = $callback();

                // Success — return immediately
                if ($result->success) {
                    return $result;
                }

                // Failure — check if retryable
                $lastResult = $result;

                if (!$this->shouldRetry($result, $retryableErrors)) {
                    Log::info("Task {$task->id} failed with non-retryable error", [
                        'error' => $result->error,
                        'attempt' => $attempt,
                    ]);
                    return $result;
                }

            } catch (\Throwable $e) {
                $lastException = $e;

                if (!$this->isRetryableException($e, $retryableErrors)) {
                    throw $e;
                }

                $lastResult = new ExecutionResult(
                    success: false,
                    error: $e->getMessage(),
                    exception: $e
                );
            }

            // Don't delay after the last attempt
            if ($attempt < $maxAttempts) {
                $delay = $this->calculateDelay($attempt);

                Log::warning("Retrying task {$task->id}, attempt {$attempt}/{$maxAttempts} after {$delay}s", [
                    'error' => $lastResult->error,
                    'exception' => $lastException?->getMessage(),
                ]);

                broadcast(new TaskRetrying(
                    taskId: $task->id,
                    attempt: $attempt,
                    maxAttempts: $maxAttempts,
                    delaySeconds: $delay,
                    error: $lastResult->error
                ));

                sleep($delay);
            }
        }

        // All attempts exhausted
        return new ExecutionResult(
            success: false,
            output: $lastResult?->output ?? '',
            error: "Failed after {$maxAttempts} attempts. Last: " . ($lastResult?->error ?? 'Unknown error'),
            metadata: array_merge($lastResult?->metadata ?? [], [
                'total_attempts' => $maxAttempts,
                'final_attempt' => true,
            ]),
            exception: $lastException
        );
    }

    /**
     * Determine if a failed result should be retried.
     */
    private function shouldRetry(ExecutionResult $result, array $retryableErrors): bool
    {
        // If result itself says it's retryable, trust it
        if ($result->isRetryable()) {
            return true;
        }

        // Check against explicit retryable error patterns
        if (empty($retryableErrors)) {
            return true; // Default: retry all failures
        }

        $haystack = strtolower($result->error . ' ' . ($result->exception?->getMessage() ?? ''));

        foreach ($retryableErrors as $pattern) {
            if (str_contains($haystack, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if an exception is retryable.
     */
    private function isRetryableException(\Throwable $e, array $retryableErrors): bool
    {
        $exceptionClass = get_class($e);
        $retryableExceptions = [
            \Symfony\Component\Process\Exception\ProcessTimedOutException::class,
            \Illuminate\Http\Client\ConnectionException::class,
            \Illuminate\Http\Client\RequestException::class,
        ];

        if (in_array($exceptionClass, $retryableExceptions, true)) {
            return true;
        }

        $message = strtolower($e->getMessage());

        foreach ($retryableErrors as $pattern) {
            if (str_contains($message, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate delay with exponential backoff + jitter.
     */
    private function calculateDelay(int $attempt): int
    {
        $base = $this->baseDelay * pow($this->backoffMultiplier, $attempt - 1);
        $jitter = random_int(0, 1000) / 1000; // 0-1 second jitter

        return (int) min($this->maxDelay, $base + $jitter);
    }
}