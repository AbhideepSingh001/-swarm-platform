<?php
// app/Services/Metrics/Contracts/MetricsCollectorInterface.php

namespace App\Services\Metrics\Contracts;

interface MetricsCollectorInterface
{
    public function collectForDriver(string $driverName, int $taskResultId, array $rawMetrics): array;

    public function collectForWorkflow(int $workflowExecutionId): array;

    public function aggregateByTask(int $taskId, array $filters = []): array;

    public function aggregateByAgent(int $agentId, array $filters = []): array;

    public function getTimeSeries(string $metric, array $filters = []): array;
}