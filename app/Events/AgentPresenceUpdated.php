<?php

namespace App\Events;

use App\Models\AgentPresence;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentPresenceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AgentPresence $presence
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('swarm.presence'),
            new Channel('agent.' . $this->presence->agent_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'agent_id' => $this->presence->agent_id,
            'status' => $this->presence->status,
            'current_channel' => $this->presence->current_channel,
            'last_seen_at' => $this->presence->last_seen_at?->toIso8601String(),
            'metadata' => $this->presence->metadata,
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.presence.updated';
    }
}