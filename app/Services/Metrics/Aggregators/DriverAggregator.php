<?php
// app/Services/Metrics/Aggregators/DriverAggregator.php

namespace App\Services\Metrics\Aggregators;
use Illuminate\Support\Facades\DB;
use App\Models\TaskResult;

class DriverAggregator extends BaseAggregator
{
    public function aggregate(string $driverName, array $filters = []): array
    {
        $query = $this->baseQuery($filters)
            ->whereRaw("JSON_EXTRACT(metadata, '$.driver') = ?", [$driverName]);

        return [
            'driver' => $driverName,
            'total_executions' => $query->clone()->count(),
            'successful' => $query->clone()->where('status', 'completed')->count(),
            'failed' => $query->clone()->where('status', 'failed')->count(),
            'success_rate' => $this->successRate($query->clone()),
            'avg_duration_ms' => (float) $query->clone()->whereNotNull('duration_ms')->avg('duration_ms'),
            'min_duration_ms' => (int) $query->clone()->whereNotNull('duration_ms')->min('duration_ms'),
            'max_duration_ms' => (int) $query->clone()->whereNotNull('duration_ms')->max('duration_ms'),
            'p50_duration_ms' => $this->percentileDuration($query->clone(), 50),
            'p95_duration_ms' => $this->percentileDuration($query->clone(), 95),
            'p99_duration_ms' => $this->percentileDuration($query->clone(), 99),
            'total_tokens' => $this->sumTokens($query->clone()),
            'avg_tokens_per_execution' => $this->avgTokens($query->clone()),
        ];
    }

    public function compareDrivers(array $driverNames, array $filters = []): array
    {
        $comparison = [];

        foreach ($driverNames as $driverName) {
            $comparison[$driverName] = $this->aggregate($driverName, $filters);
        }

        return $comparison;
    }

    private function successRate($query): float
    {
        $total = $query->count();
        if ($total === 0) {
            return 0.0;
        }

        $successful = $query->clone()->where('status', 'completed')->count();

        return round(($successful / $total) * 100, 2);
    }

    private function percentileDuration($query, int $percentile): int
    {
        $durations = $query->whereNotNull('duration_ms')->pluck('duration_ms')->toArray();

        return (int) round($this->calculatePercentile($durations, $percentile));
    }

    private function sumTokens($query): int
    {
        return (int) $query->whereNotNull('metadata')
            ->whereRaw("JSON_EXTRACT(metadata, '$.tokens.total') IS NOT NULL")
            ->sum(DB::raw("JSON_EXTRACT(metadata, '$.tokens.total')"));
    }

    private function avgTokens($query): float
    {
        $avg = $query->whereNotNull('metadata')
            ->whereRaw("JSON_EXTRACT(metadata, '$.tokens.total') IS NOT NULL")
            ->avg(DB::raw("JSON_EXTRACT(metadata, '$.tokens.total')"));

        return round((float) $avg, 2);
    }
}