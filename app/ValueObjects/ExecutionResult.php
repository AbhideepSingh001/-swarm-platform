<?php

declare(strict_types=1);

namespace App\ValueObjects;

readonly class ExecutionResult
{
    public function __construct(
        public bool $success,
        public string $output = '',
        public string $error = '',
        public array $metadata = [],
        public ?\Throwable $exception = null
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output' => $this->output,
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }

    public function isRetryable(): bool
    {
        if ($this->success) {
            return false;
        }

        // Network/transient errors are retryable
        $retryablePatterns = [
            'timeout',
            'connection',
            'rate limit',
            '429',
            '503',
            '502',
            '504',
            'temporarily unavailable',
        ];

        $haystack = strtolower($this->error . ' ' . ($this->exception?->getMessage() ?? ''));

        foreach ($retryablePatterns as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return true;
            }
        }

        return false;
    }
}