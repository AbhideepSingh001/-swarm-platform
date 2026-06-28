<?php
// app/Services/Metrics/MetricsCollector.php

namespace App\Services\Metrics;

use App\Models\TaskResult;
use App\Services\Metrics\Aggregators\DriverAggregator;
use App\Services\Metrics\Aggregators\WorkflowAggregator;
use App\Services\Metrics\Contracts\MetricsCollectorInterface;
use Illuminate\Support\Facades\DB;

class MetricsCollector implements MetricsCollectorInterface
{
    public function __construct(
        private DriverAggregator $driverAggregator,
        private WorkflowAggregator $workflowAggregator,
    ) {}

    public function collectForDriver(string $driverName, int $taskResultId, array $rawMetrics): array
    {
        return [
            'driver' => $driverName,
            'task_result_id' => $taskResultId,
            'tokens' => [
                'prompt' => $rawMetrics['tokens']['prompt'] ?? 0,
                'completion' => $rawMetrics['tokens']['completion'] ?? 0,
                'total' => ($rawMetrics['tokens']['prompt'] ?? 0) + ($rawMetrics['tokens']['completion'] ?? 0),
            ],
            'latency_ms' => $rawMetrics['latency_ms'] ?? null,
            'cost_estimate' => $rawMetrics['cost_estimate'] ?? null,
            'model' => $rawMetrics['model'] ?? null,
            'collected_at' => now()->toIso8601String(),
        ];
    }

    public function collectForWorkflow(int $workflowExecutionId): array
    {
        return $this->workflowAggregator->aggregate($workflowExecutionId);
    }

    public function aggregateByTask(int $taskId, array $filters = []): array
    {
        $query = TaskResult::where('task_id', $taskId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $results = $query->get();
        $durations = $results->pluck('duration_ms')->filter()->values();

        return [
            'task_id' => $taskId,
            'total_executions' => $results->count(),
            'successful' => $results->where('status', 'completed')->count(),
            'failed' => $results->where('status', 'failed')->count(),
            'success_rate' => (float) $this->calculateRate($results->count(), $results->where('status', 'completed')->count()),
            'avg_duration_ms' => $durations->isEmpty() ? 0 : round($durations->avg(), 2),
            'p95_duration_ms' => $durations->isEmpty() ? 0 : $this->calculatePercentile($durations->toArray(), 95),
            'total_tokens' => $results->sum(fn ($r) => $r->metadata['tokens']['total'] ?? 0),
            'last_execution_at' => $results->max('created_at'),
        ];
    }

    public function aggregateByAgent(int $agentId, array $filters = []): array
    {
        $query = TaskResult::where('agent_id', $agentId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $results = $query->get();

        return [
            'agent_id' => $agentId,
            'total_executions' => $results->count(),
            'successful' => $results->where('status', 'completed')->count(),
            'failed' => $results->where('status', 'failed')->count(),
            'success_rate' => (float) $this->calculateRate($results->count(), $results->where('status', 'completed')->count()),
            'tasks_executed' => $results->pluck('task_id')->unique()->count(),
            'avg_duration_ms' => $results->whereNotNull('duration_ms')->avg('duration_ms') ?? 0,
            'total_tokens' => $results->sum(fn ($r) => $r->metadata['tokens']['total'] ?? 0),
        ];
    }

    public function aggregateByDriver(string $driver, array $filters = []): array
    {
        $query = TaskResult::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $results = $query->get();

        return [
            'driver' => $driver,
            'total_executions' => $results->count(),
            'successful' => $results->where('status', 'completed')->count(),
            'failed' => $results->where('status', 'failed')->count(),
            'success_rate' => (float) $this->calculateRate($results->count(), $results->where('status', 'completed')->count()),
            'avg_duration_ms' => $results->whereNotNull('duration_ms')->avg('duration_ms') ?? 0,
            'total_tokens' => $results->sum(fn ($r) => $r->metadata['tokens']['total'] ?? 0),
        ];
    }

    public function getTimeSeries(string $metric, array $filters = []): array
    {
        $groupBy = $filters['group_by'] ?? 'day';
        $dateFormat = $this->getDateFormat($groupBy);

        $query = TaskResult::query();

        if (!empty($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        if (!empty($filters['agent_id'])) {
            $query->where('agent_id', $filters['agent_id']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $data = $query
            ->selectRaw("{$dateFormat} as period")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('AVG(duration_ms) as avg_duration')
            ->selectRaw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful')
            ->selectRaw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'metric' => $metric,
            'group_by' => $groupBy,
            'data' => $data->map(fn ($row) => [
                'period' => $row->period,
                'executions' => (int) $row->count,
                'successful' => (int) $row->successful,
                'failed' => (int) $row->failed,
                'avg_duration_ms' => round((float) $row->avg_duration, 2),
            ])->all(),
        ];
    }

    private function getDateFormat(string $groupBy): string
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'sqlite' => match ($groupBy) {
                'hour' => "strftime('%Y-%m-%d %H:00:00', created_at)",
                'day' => "strftime('%Y-%m-%d', created_at)",
                'week' => "strftime('%Y-%W', created_at)",
                'month' => "strftime('%Y-%m', created_at)",
                default => "strftime('%Y-%m-%d', created_at)",
            },
            'pgsql' => match ($groupBy) {
                'hour' => "TO_CHAR(created_at, 'YYYY-MM-DD HH24:00:00')",
                'day' => "TO_CHAR(created_at, 'YYYY-MM-DD')",
                'week' => "TO_CHAR(created_at, 'YYYY-IW')",
                'month' => "TO_CHAR(created_at, 'YYYY-MM')",
                default => "TO_CHAR(created_at, 'YYYY-MM-DD')",
            },
            default => match ($groupBy) { // mysql, mariadb
                'hour' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
                'day' => "DATE_FORMAT(created_at, '%Y-%m-%d')",
                'week' => "DATE_FORMAT(created_at, '%Y-%u')",
                'month' => "DATE_FORMAT(created_at, '%Y-%m')",
                default => "DATE_FORMAT(created_at, '%Y-%m-%d')",
            },
        };
    }

    private function calculateRate(int $total, int $successful): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return (float) round(($successful / $total) * 100, 2);
    }

    private function calculatePercentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, $index);

        return $values[$index];
    }
}