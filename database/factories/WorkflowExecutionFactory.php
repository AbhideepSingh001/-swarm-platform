<?php

namespace Database\Factories;

use App\Models\SwarmWorkflow;
use App\Models\WorkflowExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowExecutionFactory extends Factory
{
    protected $model = WorkflowExecution::class;

    public function definition(): array
    {
        return [
            'swarm_workflow_id' => SwarmWorkflow::factory(),
            'status' => 'pending',
            'context' => null,
            'results' => null,
            'checkpoint' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}