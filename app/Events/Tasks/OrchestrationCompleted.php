<?php

namespace App\Events\Tasks;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrchestrationCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $orchestrationId,
        public array $summary,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('orchestration.' . $this->orchestrationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'orchestration.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'orchestration_id' => $this->orchestrationId,
            'summary' => $this->summary,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}