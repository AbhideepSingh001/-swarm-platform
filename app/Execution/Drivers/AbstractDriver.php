<?php

declare(strict_types=1);

namespace App\Execution\Drivers;

use App\Contracts\ExecutionDriverInterface;
use App\Models\Task;
use App\ValueObjects\ExecutionResult;
use App\ValueObjects\SafetyLimits;
use Illuminate\Support\Facades\Log;

abstract class AbstractDriver implements ExecutionDriverInterface
{
    protected SafetyLimits $safetyLimits;
    protected int $timeoutSeconds;
    protected int $maxRetries;

    public function __construct(array $config = [])
    {
        $this->safetyLimits = SafetyLimits::fromConfig($config['safety_limits'] ?? []);
        $this->timeoutSeconds = $config['timeout'] ?? 30;
        $this->maxRetries = $config['max_retries'] ?? 3;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getRequiredResources(): array
    {
        return [
            'memory' => "{$this->safetyLimits->memoryMb}MB",
            'cpu' => "{$this->safetyLimits->cpuPercent}%",
            'timeout' => $this->timeoutSeconds,
            'network' => $this->safetyLimits->network,
        ];
    }

    /**
     * Execute a callback with safety enforcement and progress tracking.
     */
    protected function withSafety(
        callable $callback,
        ?callable $onProgress = null
    ): ExecutionResult {
        $startTime = microtime(true);

        try {
            $result = $callback();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $metadata = array_merge($result->metadata ?? [], [
                'duration_ms' => $duration,
                'driver' => $this->getName(),
            ]);

            return new ExecutionResult(
                success: $result->success,
                output: $this->truncateOutput($result->output),
                error: $result->error,
                metadata: $metadata,
                exception: $result->exception
            );

        } catch (\Throwable $e) {
            Log::error("Driver [{$this->getName()}] execution failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new ExecutionResult(
                success: false,
                output: '',
                error: $e->getMessage(),
                exception: $e
            );
        }
    }

    /**
     * Truncate output to safety limit to prevent memory exhaustion.
     */
    protected function truncateOutput(string $output): string
    {
        $maxBytes = $this->safetyLimits->maxOutputMb * 1024 * 1024;

        if (strlen($output) <= $maxBytes) {
            return $output;
        }

        $truncated = substr($output, 0, $maxBytes);
        $truncated .= "\n\n[OUTPUT TRUNCATED: exceeded {$this->safetyLimits->maxOutputMb}MB limit]";

        return $truncated;
    }

    /**
     * Report progress if callback provided.
     */
    protected function reportProgress(int $percent, string $message = '', ?callable $onProgress = null): void
    {
        if ($onProgress !== null) {
            $onProgress($percent, $message);
        }
    }
}