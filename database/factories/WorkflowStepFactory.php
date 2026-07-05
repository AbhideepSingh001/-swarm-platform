<?php

namespace Database\Factories;

use App\Models\SwarmWorkflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowStepFactory extends Factory
{
    protected $model = WorkflowStep::class;

    public function definition(): array
    {
        return [
            'swarm_workflow_id' => SwarmWorkflow::factory(),
            'name' => $this->faker->word(),
            'agent' => $this->faker->word(),
            'task' => $this->faker->word(),
            'config' => null,
            'depends_on' => [],
            'order' => 0,
            'max_retries' => 0,
        ];
    }
}