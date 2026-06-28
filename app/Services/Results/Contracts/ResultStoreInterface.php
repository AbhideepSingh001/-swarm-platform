<?php
// app/Services/Results/Contracts/ResultStoreInterface.php

namespace App\Services\Results\Contracts;

use App\Models\TaskResult;

interface ResultStoreInterface
{
    public function create(int $taskId, ?int $agentId = null, ?int $workflowExecutionId = null): TaskResult;

    public function find(int $id): ?TaskResult;

    public function findByTask(int $taskId, array $filters = []): array;

    public function update(int $id, array $data): TaskResult;

    public function delete(int $id): bool;

    public function log(int $taskResultId, string $level, string $message, ?string $phase = null, ?array $context = null): void;

    public function attachArtifact(int $taskResultId, string $name, string $type, string $disk, string $path, ?int $sizeBytes = null, ?array $metadata = null): void;

    public function getArtifacts(int $taskResultId): array;
}