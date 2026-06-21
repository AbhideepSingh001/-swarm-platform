<?php

namespace Database\Factories;

use App\Models\SwarmSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class SwarmSessionFactory extends Factory
{
    protected $model = SwarmSession::class;

    public function definition(): array
    {
        return [
            'goal' => fake()->sentence(),
            'status' => fake()->randomElement(['active', 'paused', 'completed', 'failed']),
            'current_phase' => fake()->randomElement(['planning', 'execution', 'review', 'idle']),
            'started_at' => fake()->optional()->dateTime(),
            'completed_at' => null,
        ];
    }
}