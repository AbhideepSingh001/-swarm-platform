<?php

namespace App\Agents\Executor;

class ExecutionTask
{
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly array $config = [],
        public readonly int $maxRetries = 3,
        public readonly ?string $callbackUrl = null,
    ) {}
}