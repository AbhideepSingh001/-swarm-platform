<?php

namespace App\Events\Tasks;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCommentAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public TaskComment $comment,
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
        return 'task.comment.added';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'comment' => $this->comment->load('commentable'),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}