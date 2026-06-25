<?php

use App\Models\Agent;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Agent private channel - only the agent itself can listen
Broadcast::channel('agent.{agentId}', function ($user, int $agentId) {
    $agent = Agent::find($agentId);
    
    return $agent !== null; // Add your auth logic here
});

// Swarm-wide channel (for messages, tasks, etc.)
Broadcast::channel('swarm.{channel}', function ($user, string $channel) {
    return true; // Add appropriate authorization
});

// Day 13: Swarm presence channel - who is online
Broadcast::channel('swarm.presence', function ($user) {
    return true; // Add appropriate authorization
});