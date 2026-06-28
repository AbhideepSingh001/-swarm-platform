<?php

namespace App\Services\Analytics;

use App\Models\Task;
use App\Models\TaskResult;

class WorkflowAggregator
{
    public function aggregate(int $taskId, array $filters = []): array
    {
        $task = Task::findOrFail($taskId);

        $query = TaskResult::where('task_id', $taskId);

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $total = $query->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $failed = (clone $query)->where('status', 'failed')->count();
        $avgDuration = (clone $query)->whereNotNull('duration_ms')->avg('duration_ms');

        return [
            'task_id' => $taskId,
            'task_name' => $task->title,
            'total_executions' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
            'avg_duration_ms' => round($avgDuration ?? 0, 2),
        ];
    }
}