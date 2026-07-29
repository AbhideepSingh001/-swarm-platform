<?php

namespace Database\Factories;

use App\Models\DeadLetter;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeadLetterFactory extends Factory
{
    protected $model = DeadLetter::class;

    public function definition(): array
    {
        return [
            'execution_id' => 'exec-' . $this->faker->uuid,
            'step_id' => 'step-' . $this->faker->randomNumber(3),
            'agent_id' => 'agent-' . $this->faker->word,
            'failure_category' => 'unknown',
            'error_message' => $this->faker->sentence,
            'error_trace' => [],
            'step_config' => [],
            'context' => [],
            'retry_count' => 3,
            'failed_at' => now(),
            'status' => 'open',
        ];
    }
}
