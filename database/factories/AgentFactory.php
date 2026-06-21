<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\SwarmSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'session_id' => SwarmSession::factory(),
            'role' => fake()->randomElement(['planner', 'executor', 'coordinator', 'analyzer', 'general']),
            'name' => fake()->unique()->word() . '_agent',
            'status' => fake()->randomElement(['idle', 'busy', 'offline']),
            'model' => fake()->randomElement(['gpt-4', 'gpt-3.5', 'claude', 'llama']),
            'memory' => null,
        ];
    }
}