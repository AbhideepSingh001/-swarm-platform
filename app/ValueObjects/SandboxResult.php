<?php

declare(strict_types=1);

namespace App\ValueObjects;

readonly class SandboxResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $error,
        public float $durationMs,
        public ?float $memoryPeakMb = null,
        public ?float $cpuPercent = null
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function toArray(): array
    {
        return [
            'exit_code' => $this->exitCode,
            'output' => $this->output,
            'error' => $this->error,
            'duration_ms' => $this->durationMs,
            'memory_peak_mb' => $this->memoryPeakMb,
            'cpu_percent' => $this->cpuPercent,
        ];
    }
}