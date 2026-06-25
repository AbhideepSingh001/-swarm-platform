<?php

namespace App\Events\Tasks;

use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public TaskAssignment $assignment,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('tasks'),
            new Channel('task.' . $this->task->id),
            new Channel('orchestration.' . $this->task->orchestration_id),
            new Channel('agent.' . $this->assignment->assignable_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'task' => $this->task->load(['assignments.assignable', 'creator']),
            'assignment' => $this->assignment->load('assignable'),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}