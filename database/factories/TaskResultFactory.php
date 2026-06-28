<?php
// database/factories/TaskResultFactory.php

namespace Database\Factories;

use App\Models\TaskResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskResultFactory extends Factory
{
    protected $model = TaskResult::class;

    public function definition(): array
    {
        return [
            'task_id' => \App\Models\Task::factory(),
            'agent_id' => null,
            'workflow_execution_id' => null,
            'status' => 'pending',
            'output' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'duration_ms' => null,
            'metadata' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'output' => ['result' => 'success'],
            'started_at' => now()->subSeconds(5),
            'completed_at' => now(),
            'duration_ms' => 5000,
            'metadata' => ['tokens' => ['prompt' => 100, 'completion' => 50, 'total' => 150]],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'Something went wrong',
            'started_at' => now()->subSeconds(3),
            'completed_at' => now(),
            'duration_ms' => 3000,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => now(),
        ]);
    }
}