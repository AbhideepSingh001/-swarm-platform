<?php

namespace App\Services\Analytics;

use App\Models\TaskResult;

class DriverAggregator
{
    public function aggregate(string $driver, array $filters = []): array
    {
        $query = TaskResult::query()
            ->whereHas('task', function ($q) use ($driver) {
                $q->where('driver', $driver);
            });

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

        $topErrors = (clone $query)
            ->whereNotNull('error_message')
            ->selectRaw('error_message, COUNT(*) as count')
            ->groupBy('error_message')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'error_message')
            ->toArray();

        return [
            'driver' => $driver,
            'total_executions' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
            'avg_duration_ms' => round($avgDuration ?? 0, 2),
            'top_errors' => $topErrors,
        ];
    }
}