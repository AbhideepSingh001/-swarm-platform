<?php

namespace App\Events;

use App\Models\Agent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentWentOnline implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Agent $agent
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('swarm.presence'),
            new Channel('agent.' . $this->agent->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'agent_id' => $this->agent->id,
            'name' => $this->agent->name,
            'status' => 'online',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.online';
    }
}