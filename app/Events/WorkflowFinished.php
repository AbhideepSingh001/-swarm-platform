<?php

namespace App\Events;

use App\Models\WorkflowExecution;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowFinished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkflowExecution $execution,
        public string $finalStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workflow.' . $this->execution->id),
            new PrivateChannel('workflows'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'workflow.finished';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'workflow_name' => $this->execution->workflow->name,
            'status' => $this->finalStatus,
            'results' => $this->execution->results,
            'started_at' => $this->execution->started_at?->toIso8601String(),
            'finished_at' => $this->execution->finished_at?->toIso8601String(),
        ];
    }
}