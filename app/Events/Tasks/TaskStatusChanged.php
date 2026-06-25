<?php

namespace App\Events\Tasks;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public string $oldStatus,
        public string $newStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('tasks'),
            new Channel('task.' . $this->task->id),
            new Channel('orchestration.' . $this->task->orchestration_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'orchestration_id' => $this->task->orchestration_id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'task' => $this->task->load(['assignments.assignable']),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}