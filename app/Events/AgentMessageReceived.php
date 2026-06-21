<?php

namespace App\Events;

use App\Models\AgentMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;  // <-- ADD THIS
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AgentMessage $message,
        public int $targetAgentId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("agent.{$this->targetAgentId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'channel' => $this->message->channel,
            'type' => $this->message->type,
            'payload' => $this->message->payload,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.message.received';
    }
}