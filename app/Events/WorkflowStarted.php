<?php

namespace App\Events;

use App\Models\WorkflowExecution;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WorkflowExecution $execution) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workflow.' . $this->execution->id),
            new PrivateChannel('workflows'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'workflow.started';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'workflow_name' => $this->execution->workflow->name,
            'status' => $this->execution->status,
            'started_at' => $this->execution->started_at?->toIso8601String(),
        ];
    }
}