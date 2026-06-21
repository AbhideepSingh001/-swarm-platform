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
    // You might want to check if the user owns this agent
    // or if the agent's API token is valid
    $agent = Agent::find($agentId);
    
    return $agent !== null; // Add your auth logic here
});

// Swarm-wide channel
Broadcast::channel('swarm.{channel}', function ($user, string $channel) {
    return true; // Add appropriate authorization
});