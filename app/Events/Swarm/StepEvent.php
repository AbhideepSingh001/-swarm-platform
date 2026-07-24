<?php

declare(strict_types=1);

namespace App\Events\Swarm;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StepEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $event,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('swarm.execution.' . ($this->payload['execution_id'] ?? 'unknown')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'swarm.' . $this->event;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
