<?php

namespace App\Events;

use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StepCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkflowExecution $execution,
        public WorkflowStep $step,
        public mixed $result,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workflow.' . $this->execution->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'step.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'step_name' => $this->step->name,
            'agent' => $this->step->agent,
            'result' => $this->result,
            'completed_at' => now()->toIso8601String(),
        ];
    }
}