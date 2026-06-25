<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TaskOutputChunked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $taskId,
        public string $chunk,
        public int $tokenCount
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('tasks.' . $this->taskId)];
    }

    public function broadcastAs(): string
    {
        return 'task.output.chunked';
    }
}