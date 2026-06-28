<?php
// app/Http/Controllers/Api/AnalyticsController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskResult;
use App\Services\Metrics\Aggregators\DriverAggregator;
use App\Services\Metrics\Aggregators\WorkflowAggregator;
use App\Services\Metrics\Contracts\MetricsCollectorInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private MetricsCollectorInterface $metricsCollector,
        private DriverAggregator $driverAggregator,
        private WorkflowAggregator $workflowAggregator,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $validated['from'] ?? now()->subDays(30)->startOfDay();
        $to = $validated['to'] ?? now()->endOfDay();

        $query = TaskResult::whereBetween('created_at', [$from, $to]);

        $total = $query->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        return response()->json([
            'data' => [
                'total_executions' => $total,
                'completed' => $completed,
                'failed' => $failed,
                'success_rate' => $total > 0 ? (float) round(($completed / $total) * 100, 2) : 0.0,
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function statusDistribution(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $validated['from'] ?? now()->subDays(30)->startOfDay();
        $to = $validated['to'] ?? now()->endOfDay();

        $distribution = TaskResult::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return response()->json([
            'data' => [
                'completed' => $distribution['completed'] ?? 0,
                'failed' => $distribution['failed'] ?? 0,
                'pending' => $distribution['pending'] ?? 0,
                'running' => $distribution['running'] ?? 0,
                'cancelled' => $distribution['cancelled'] ?? 0,
            ],
        ]);
    }

    public function dailyTrends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $days = $validated['days'] ?? 30;
        $from = $validated['from'] ?? now()->subDays($days)->startOfDay();
        $to = $validated['to'] ?? now()->endOfDay();

        $results = TaskResult::whereBetween('created_at', [$from, $to])
            ->get()
            ->groupBy(fn ($r) => $r->created_at->format('Y-m-d'));

        $trends = [];
        $current = \Carbon\Carbon::parse($from);
        $end = \Carbon\Carbon::parse($to);

        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $dayResults = $results[$date] ?? collect();

            $trends[] = [
                'date' => $date,
                'completed' => $dayResults->where('status', 'completed')->count(),
                'failed' => $dayResults->where('status', 'failed')->count(),
                'pending' => $dayResults->where('status', 'pending')->count(),
                'running' => $dayResults->where('status', 'running')->count(),
            ];

            $current->addDay();
        }

        return response()->json(['data' => $trends]);
    }

    public function task(Task $task): JsonResponse
    {
        $metrics = $this->metricsCollector->aggregateByTask($task->id);
        return response()->json(['data' => $metrics], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function driver(string $driver): JsonResponse
    {
        $metrics = $this->metricsCollector->aggregateByDriver($driver);
        return response()->json(['data' => $metrics], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function taskMetrics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:pending,running,completed,failed,cancelled'],
        ]);

        $filters = array_filter([
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'status' => $validated['status'] ?? null,
        ]);

        $metrics = $this->metricsCollector->aggregateByTask($validated['task_id'], $filters);

        // Ensure success_rate is float with decimal preserved
        $metrics['success_rate'] = (float) $metrics['success_rate'];

        return response()->json(['data' => $metrics], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function agentMetrics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:pending,running,completed,failed,cancelled'],
        ]);

        $filters = array_filter([
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'status' => $validated['status'] ?? null,
        ]);

        return response()->json([
            'data' => $this->metricsCollector->aggregateByAgent($validated['agent_id'], $filters),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function workflowMetrics(int $workflowExecutionId): JsonResponse
    {
        return response()->json([
            'data' => $this->metricsCollector->collectForWorkflow($workflowExecutionId),
        ]);
    }

    public function workflowTrend(int $workflowId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'last_n' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'data' => $this->workflowAggregator->trendAnalysis(
                $workflowId,
                $validated['last_n'] ?? 10,
            ),
        ]);
    }

    public function driverMetrics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver' => ['required', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:pending,running,completed,failed,cancelled'],
        ]);

        $filters = array_filter([
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'status' => $validated['status'] ?? null,
        ]);

        return response()->json([
            'data' => $this->driverAggregator->aggregate($validated['driver'], $filters),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function compareDrivers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'drivers' => ['required', 'array', 'min:2'],
            'drivers.*' => ['string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $filters = array_filter([
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ]);

        return response()->json([
            'data' => $this->driverAggregator->compareDrivers($validated['drivers'], $filters),
        ]);
    }

    public function timeSeries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metric' => ['required', 'string', 'in:executions,success_rate,duration,tokens'],
            'group_by' => ['nullable', 'string', 'in:hour,day,week,month'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $filters = array_filter([
            'group_by' => $validated['group_by'] ?? 'day',
            'task_id' => $validated['task_id'] ?? null,
            'agent_id' => $validated['agent_id'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ]);

        $result = $this->metricsCollector->getTimeSeries($validated['metric'], $filters);

        return response()->json([
            'data' => [
                'metric' => $result['metric'],
                'group_by' => $result['group_by'],
                'data' => array_values($result['data']),
            ],
        ]);
    }

    public function dashboardSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $filters = array_filter([
            'from' => $validated['from'] ?? now()->subDays(7)->startOfDay(),
            'to' => $validated['to'] ?? now()->endOfDay(),
        ]);

        $query = TaskResult::query()
            ->whereBetween('created_at', [$filters['from'], $filters['to']]);

        $total = $query->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $filters['from'],
                    'to' => $filters['to'],
                ],
                'total_executions' => $total,
                'completed' => $completed,
                'failed' => $failed,
                'success_rate' => $total > 0 ? (float) round(($completed / $total) * 100, 2) : 0.0,
                'avg_duration_ms' => (clone $query)->whereNotNull('duration_ms')->avg('duration_ms') ?? 0,
                'active_tasks' => (clone $query)->distinct('task_id')->count('task_id'),
                'active_agents' => (clone $query)->whereNotNull('agent_id')->distinct('agent_id')->count('agent_id'),
                'recent_failures' => (clone $query)
                    ->where('status', 'failed')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'task_id', 'error_message', 'created_at']),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}