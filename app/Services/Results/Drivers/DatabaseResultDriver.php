<?php

namespace App\Services\Results\Drivers;

use App\Models\Artifact;
use App\Models\TaskExecutionLog;
use App\Models\TaskResult;
use App\Services\Results\Contracts\ResultStoreInterface;
use Illuminate\Support\Facades\DB;

class DatabaseResultDriver implements ResultStoreInterface
{
    public function create(int $taskId, ?int $agentId = null, ?int $workflowExecutionId = null): TaskResult
    {
        return TaskResult::create([
            'task_id' => $taskId,
            'agent_id' => $agentId,
            'workflow_execution_id' => $workflowExecutionId,
            'status' => 'pending',
        ]);
    }

    public function find(int $id): ?TaskResult
    {
        return TaskResult::with(['executionLogs', 'artifacts', 'task', 'agent'])->find($id);
    }

    public function findByTask(int $taskId, array $filters = []): array
    {
        $query = TaskResult::where('task_id', $taskId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['workflow_execution_id'])) {
            $query->where('workflow_execution_id', $filters['workflow_execution_id']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $query->orderBy('created_at', 'desc');

        return $query->get()->all();
    }

    public function update(int $id, array $data): TaskResult
    {
        $result = TaskResult::findOrFail($id);
        $result->update($data);

        return $result->fresh(['executionLogs', 'artifacts']);
    }

    public function delete(int $id): bool
    {
        $result = TaskResult::find($id);

        if (!$result) {
            return false;
        }

        return DB::transaction(function () use ($result) {
            // Artifacts delete their files via model boot
            $result->artifacts()->delete();
            $result->executionLogs()->delete();

            return $result->delete();
        });
    }

    public function log(int $taskResultId, string $level, string $message, ?string $phase = null, ?array $context = null): void
    {
        TaskExecutionLog::create([
            'task_result_id' => $taskResultId,
            'level' => $level,
            'phase' => $phase,
            'message' => $message,
            'context' => $context,
            'logged_at' => now(),
        ]);
    }

    public function attachArtifact(int $taskResultId, string $name, string $type, string $disk, string $path, ?int $sizeBytes = null, ?array $metadata = null): void
    {
        Artifact::create([
            'task_result_id' => $taskResultId,
            'name' => $name,
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $this->inferMimeType($type),
            'size_bytes' => $sizeBytes,
            'metadata' => $metadata,
        ]);
    }

    public function getArtifacts(int $taskResultId): array
    {
        return Artifact::where('task_result_id', $taskResultId)->get()->all();
    }

    public function query(array $filters = [])
    {
        $query = TaskResult::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (!empty($filters['search'])) {
            $query->where('error_message', 'like', '%' . $filters['search'] . '%');
        }

        return $query;
    }

    private function inferMimeType(string $type): string
    {
        return match ($type) {
            'json' => 'application/json',
            'csv' => 'text/csv',
            'image' => 'image/png',
            'text' => 'text/plain',
            'binary' => 'application/octet-stream',
            default => 'application/octet-stream',
        };
    }
}