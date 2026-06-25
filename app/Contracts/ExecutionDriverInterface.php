<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Task;
use App\ValueObjects\ExecutionResult;

interface ExecutionDriverInterface
{
    /**
     * Unique driver identifier (e.g., 'code', 'llm', 'shell', 'http')
     */
    public function getName(): string;

    /**
     * Validate that the task payload is compatible with this driver.
     */
    public function validatePayload(array $payload): bool;

    /**
     * Execute the task. Returns ExecutionResult on completion or throws on unrecoverable error.
     */
    public function execute(Task $task, array $config = []): ExecutionResult;

    /**
     * Resource requirements for scheduling/limits.
     */
    public function getRequiredResources(): array;

    /**
     * Maximum retry attempts for this driver.
     */
    public function getMaxRetries(): int;
}