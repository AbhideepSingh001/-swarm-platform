<?php

namespace App\Services\Swarm;

use App\Models\WorkflowExecution;

class WorkflowStateMachine
{
    private const TRANSITIONS = [
        'pending' => ['running', 'cancelled'],
        'running' => ['paused', 'completed', 'failed', 'cancelled'],
        'paused' => ['running', 'cancelled'],
        'completed' => [],
        'failed' => [],
        'cancelled' => [],
    ];

    public function canTransition(WorkflowExecution $execution, string $to): bool
    {
        $from = $execution->status;
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

        public function transition(WorkflowExecution $execution, string $to): void
    {
        if (!$this->canTransition($execution, $to)) {
            throw new \InvalidArgumentException(
                "Cannot transition from '{$execution->status}' to '{$to}'"
            );
        }

        $updates = ['status' => $to];

        if ($to === 'running' && $execution->started_at === null) {
            $updates['started_at'] = now();
        }

        if (in_array($to, ['completed', 'failed', 'cancelled'], true)) {
            $updates['finished_at'] = now();
        }

        $execution->update($updates);
    }

    public function pause(WorkflowExecution $execution): void
    {
        $this->transition($execution, 'paused');
    }

    public function resume(WorkflowExecution $execution): void
    {
        $this->transition($execution, 'running');
    }

    public function cancel(WorkflowExecution $execution): void
    {
        $this->transition($execution, 'cancelled');
    }

    public function complete(WorkflowExecution $execution): void
    {
        $this->transition($execution, 'completed');
    }

    public function fail(WorkflowExecution $execution): void
    {
        $this->transition($execution, 'failed');
    }

    public function start(WorkflowExecution $execution): void
    {
        $this->transition($execution, 'running');
    }

    public function getAllowedTransitions(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public function isTerminal(string $status): bool
    {
        return empty(self::TRANSITIONS[$status] ?? []);
    }
}