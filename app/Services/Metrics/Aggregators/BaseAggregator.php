<?php
// app/Services/Metrics/Aggregators/BaseAggregator.php

namespace App\Services\Metrics\Aggregators;

use App\Models\TaskResult;
use Illuminate\Support\Facades\DB;

abstract class BaseAggregator
{
    protected function baseQuery(array $filters = [])
    {
        $query = TaskResult::query();

        if (!empty($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        if (!empty($filters['agent_id'])) {
            $query->where('agent_id', $filters['agent_id']);
        }

        if (!empty($filters['workflow_execution_id'])) {
            $query->where('workflow_execution_id', $filters['workflow_execution_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query;
    }

    protected function calculatePercentile(array $values, float $percentile): float
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