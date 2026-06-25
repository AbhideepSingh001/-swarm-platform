<?php

namespace App\Events\Tasks;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public int $oldProgress,
        public int $newProgress,
        public ?string $message = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('task.' . $this->task->id),
            new Channel('orchestration.' . $this->task->orchestration_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.progress.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'old_progress' => $this->oldProgress,
            'new_progress' => $this->newProgress,
            'message' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}