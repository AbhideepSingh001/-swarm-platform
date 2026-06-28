<?php

namespace App\Services\Results;

use App\Services\Results\Contracts\ResultStoreInterface;
use App\Models\TaskResult;

class ResultStore
{
    public function __construct(
        private ResultStoreInterface $driver
    ) {}

    public function create(int $taskId, ?int $agentId = null, ?int $workflowExecutionId = null): TaskResult
    {
        return $this->driver->create($taskId, $agentId, $workflowExecutionId);
    }

    public function update(int $id, array $data): TaskResult
    {
        return $this->driver->update($id, $data);
    }

    public function find(int $id): ?TaskResult
    {
        return $this->driver->find($id);
    }

    public function findByTask(int $taskId, array $filters = []): array
    {
        return $this->driver->findByTask($taskId, $filters);
    }

    public function delete(int $id): bool
    {
        return $this->driver->delete($id);
    }

    public function query(array $filters = [])
    {
        return $this->driver->query($filters);
    }

    public function log(int $taskResultId, string $level, string $message, ?string $phase = null, ?array $context = null): void
    {
        $this->driver->log($taskResultId, $level, $message, $phase, $context);
    }

    public function attachArtifact(int $taskResultId, string $name, string $type, string $disk, string $path, ?int $sizeBytes = null, ?array $metadata = null): void
    {
        $this->driver->attachArtifact($taskResultId, $name, $type, $disk, $path, $sizeBytes, $metadata);
    }

    public function getArtifacts(int $taskResultId): array
    {
        return $this->driver->getArtifacts($taskResultId);
    }
}