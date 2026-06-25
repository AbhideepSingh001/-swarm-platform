<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'task_id' => 'task_' . uniqid(),
            'orchestration_id' => 'orch_' . uniqid(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'pending',
            'priority' => 'medium',
            'task_type' => 'execution',  // REQUIRED - NOT NULL in DB
            'retry_count' => 0,
            'max_retries' => 3,
            'plan_id' => null,
            'parent_task_id' => null,
            'creator_id' => null,
            'agent_type' => null,
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'scheduled_at' => null,
            'deadline_at' => null,
            'estimated_duration_minutes' => null,
            'actual_duration_minutes' => null,
            'depends_on' => null,
            'config' => null,
            'result' => null,
            'last_error' => null,
            'metadata' => null,
            // Day 15 fields
            'driver' => fake()->randomElement(['code', 'llm', 'shell', 'http']),
            'payload' => null,
            'output' => null,
            'attempts' => 0,
        ];
    }

    public function withPayload(array $payload): self
    {
        return $this->state(fn () => ['payload' => $payload]);
    }

    public function withDriver(string $driver): self
    {
        return $this->state(fn () => ['driver' => $driver]);
    }

    public function pending(): self
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function running(): self
    {
        return $this->state(fn () => [
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'last_error' => fake()->sentence(),
        ]);
    }
}