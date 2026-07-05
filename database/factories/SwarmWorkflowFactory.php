<?php

namespace Database\Factories;

use App\Models\SwarmWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

class SwarmWorkflowFactory extends Factory
{
    protected $model = SwarmWorkflow::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),
            'config' => null,
            'is_active' => true,
        ];
    }
}