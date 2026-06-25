<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Task;

class AgentLoadBalancer
{
    public function selectAgent(Task $task): ?Agent
    {
        $candidates = Agent::where('status', 'online')
            ->withCount(['assignments as active_tasks' => function ($query) {
                $query->whereHas('task', function ($q) {
                    $q->whereIn('status', ['assigned', 'in_progress']);
                });
            }])
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sortBy(function (Agent $agent) {
            return $agent->active_tasks * 10;
        })->first();
    }
}