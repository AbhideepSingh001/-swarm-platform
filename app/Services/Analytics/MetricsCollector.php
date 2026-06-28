<?php

namespace App\Services\Analytics;

use App\Models\TaskResult;
use Illuminate\Database\Eloquent\Collection;

class MetricsCollector
{
    public function __construct(
        private DriverAggregator $driverAggregator,
        private WorkflowAggregator $workflowAggregator,
    ) {}

    public function forDriver(string $driver, array $filters = []): array
    {
        return $this->driverAggregator->aggregate($driver, $filters);
    }

    public function forWorkflow(int $taskId, array $filters = []): array
    {
        return $this->workflowAggregator->aggregate($taskId, $filters);
    }

    public function summary(array $filters = []): array
    {
        $query = TaskResult::query();

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
            'total_executions' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
            'avg_duration_ms' => round($avgDuration ?? 0, 2),
        ];
    }

    public function statusDistribution(array $filters = []): array
    {
        $query = TaskResult::query();

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function dailyTrends(int $days = 30, array $filters = []): array
    {
        $query = TaskResult::query()
            ->where('created_at', '>=', now()->subDays($days));

        return $query
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as count')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(fn (Collection $day) => [
                'date' => $day->first()->date,
                'completed' => $day->firstWhere('status', 'completed')?->count ?? 0,
                'failed' => $day->firstWhere('status', 'failed')?->count ?? 0,
                'pending' => $day->firstWhere('status', 'pending')?->count ?? 0,
                'running' => $day->firstWhere('status', 'running')?->count ?? 0,
            ])
            ->values()
            ->toArray();
    }
}