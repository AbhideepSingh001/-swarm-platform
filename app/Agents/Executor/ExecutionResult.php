<?php

namespace App\Agents\Executor;

class ExecutionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output,
        public readonly ?string $error = null,
        public readonly int $exitCode = 0,
        public readonly array $metadata = [],
        public readonly float $executionTime = 0.0,
    ) {}

    public static function success(string $output, array $metadata = [], float $time = 0.0): self
    {
        return new self(true, $output, null, 0, $metadata, $time);
    }

    public static function failure(string $error, int $exitCode = 1, array $metadata = [], float $time = 0.0): self
    {
        return new self(false, '', $error, $exitCode, $metadata, $time);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output' => $this->output,
            'error' => $this->error,
            'exit_code' => $this->exitCode,
            'metadata' => $this->metadata,
            'execution_time' => $this->executionTime,
        ];
    }
}