<?php
// app/Services/Metrics/Aggregators/WorkflowAggregator.php

namespace App\Services\Metrics\Aggregators;

use App\Models\TaskResult;
use App\Models\WorkflowExecution;

class WorkflowAggregator extends BaseAggregator
{
    public function aggregate(int $workflowExecutionId): array
    {
        $execution = WorkflowExecution::with('taskResults')->find($workflowExecutionId);

        if (!$execution) {
            return ['error' => 'Workflow execution not found'];
        }

        $results = $execution->taskResults;
        $durations = $results->pluck('duration_ms')->filter()->values();

        return [
            'workflow_execution_id' => $workflowExecutionId,
            'status' => $execution->status,
            'total_tasks' => $results->count(),
            'completed' => $results->where('status', 'completed')->count(),
            'failed' => $results->where('status', 'failed')->count(),
            'pending' => $results->where('status', 'pending')->count(),
            'running' => $results->where('status', 'running')->count(),
            'success_rate' => $this->calculateSuccessRate($results),
            'total_duration_ms' => $durations->sum(),
            'avg_task_duration_ms' => $durations->isEmpty() ? 0 : (int) round($durations->avg()),
            'min_task_duration_ms' => $durations->isEmpty() ? 0 : $durations->min(),
            'max_task_duration_ms' => $durations->isEmpty() ? 0 : $durations->max(),
            'bottleneck_task' => $this->findBottleneck($results),
            'critical_path' => $this->calculateCriticalPath($results),
            'total_tokens' => $this->sumWorkflowTokens($results),
            'started_at' => $execution->started_at,
            'completed_at' => $execution->completed_at,
        ];
    }

    public function compareExecutions(array $executionIds): array
    {
        $comparison = [];

        foreach ($executionIds as $id) {
            $comparison[$id] = $this->aggregate($id);
        }

        return $comparison;
    }

    public function trendAnalysis(int $workflowId, int $lastN = 10): array
    {
        $executions = WorkflowExecution::where('workflow_id', $workflowId)
            ->orderBy('created_at', 'desc')
            ->limit($lastN)
            ->get();

        $trends = [];

        foreach ($executions as $execution) {
            $metrics = $this->aggregate($execution->id);
            $trends[] = [
                'execution_id' => $execution->id,
                'created_at' => $execution->created_at,
                'success_rate' => $metrics['success_rate'],
                'total_duration_ms' => $metrics['total_duration_ms'],
                'total_tokens' => $metrics['total_tokens'],
            ];
        }

        return [
            'workflow_id' => $workflowId,
            'executions_analyzed' => count($trends),
            'avg_success_rate' => $this->calculateTrendAvg($trends, 'success_rate'),
            'avg_duration_ms' => $this->calculateTrendAvg($trends, 'total_duration_ms'),
            'avg_tokens' => $this->calculateTrendAvg($trends, 'total_tokens'),
            'trend_direction' => $this->determineTrendDirection($trends),
            'data' => array_reverse($trends), // chronological order
        ];
    }

    private function calculateSuccessRate($results): float
    {
        $total = $results->count();
        if ($total === 0) {
            return 0.0;
        }

        $completed = $results->where('status', 'completed')->count();

        return round(($completed / $total) * 100, 2);
    }

    private function findBottleneck($results): ?array
    {
        $slowest = $results->whereNotNull('duration_ms')->sortByDesc('duration_ms')->first();

        if (!$slowest) {
            return null;
        }

        return [
            'task_id' => $slowest->task_id,
            'task_name' => $slowest->task->name ?? null,
            'duration_ms' => $slowest->duration_ms,
            'percentage_of_total' => $this->calculateBottleneckPercentage($results, $slowest->duration_ms),
        ];
    }

    private function calculateBottleneckPercentage($results, int $bottleneckDuration): float
    {
        $total = $results->sum('duration_ms');
        if ($total === 0) {
            return 0.0;
        }

        return round(($bottleneckDuration / $total) * 100, 2);
    }

    private function calculateCriticalPath($results): array
    {
        return $results
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->sortBy('started_at')
            ->map(fn ($r) => [
                'task_id' => $r->task_id,
                'task_name' => $r->task->name ?? null,
                'started_at' => $r->started_at,
                'completed_at' => $r->completed_at,
                'duration_ms' => $r->duration_ms,
            ])
            ->values()
            ->all();
    }

    private function sumWorkflowTokens($results): int
    {
        return $results->sum(function ($result) {
            return $result->metadata['tokens']['total'] ?? 0;
        });
    }

    private function calculateTrendAvg(array $trends, string $key): float
    {
        if (empty($trends)) {
            return 0.0;
        }

        $values = array_column($trends, $key);
        $sum = array_sum($values);
        $count = count($values);

        return round($sum / $count, 2);
    }

    private function determineTrendDirection(array $trends): string
    {
        if (count($trends) < 2) {
            return 'insufficient_data';
        }

        $first = $trends[0]['success_rate'] ?? 0;
        $last = $trends[count($trends) - 1]['success_rate'] ?? 0;

        $diff = $last - $first;

        return match (true) {
            $diff > 5 => 'improving',
            $diff < -5 => 'degrading',
            default => 'stable',
        };
    }
}