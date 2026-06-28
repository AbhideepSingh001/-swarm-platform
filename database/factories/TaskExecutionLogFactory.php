<?php
// database/factories/TaskExecutionLogFactory.php

namespace Database\Factories;

use App\Models\TaskExecutionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskExecutionLogFactory extends Factory
{
    protected $model = TaskExecutionLog::class;

    public function definition(): array
    {
        return [
            'task_result_id' => \App\Models\TaskResult::factory(),
            'level' => $this->faker->randomElement(['debug', 'info', 'warning', 'error']),
            'phase' => $this->faker->randomElement(['initialization', 'execution', 'cleanup']),
            'message' => $this->faker->sentence(),
            'context' => null,
            'logged_at' => now(),
        ];
    }
}