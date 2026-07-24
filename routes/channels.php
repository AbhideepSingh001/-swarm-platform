<?php

use App\Models\Agent;
use Illuminate\Support\Facades\Broadcast;

require __DIR__.'/channels_workflows.php';

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// ─── Agent private channel ─────────────────────────────────
Broadcast::channel('agent.{agentId}', function ($user, int $agentId) {
    $agent = Agent::find($agentId);
    return $agent !== null;
});

// ─── Swarm-wide channel ─────────────────────────────────────
Broadcast::channel('swarm.{channel}', function ($user, string $channel) {
    return true;
});

// ─── Swarm presence channel ─────────────────────────────────
Broadcast::channel('swarm.presence', function ($user) {
    return true;
});

// ═══════════════════════════════════════════════════════════
// DAY 18: Async Queue Execution Channels
// ═══════════════════════════════════════════════════════════

// Workflow execution progress (step events, level completion)
Broadcast::channel('swarm.execution.{executionId}', function ($user, string $executionId) {
    // TODO: Add proper auth — check if user owns this execution
    // Example:
    // $execution = \App\Models\WorkflowExecution::find($executionId);
    // return $execution && $execution->user_id === $user->id;

    return true; // Open for development — lock down before production
});

// Batch-level progress (aggregate stats)
Broadcast::channel('swarm.batch.{batchId}', function ($user, string $batchId) {
    // TODO: Same auth as above
    return true;
});