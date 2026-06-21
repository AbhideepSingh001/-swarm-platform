<?php

namespace Database\Factories;

use App\Models\AgentMessage;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentMessageFactory extends Factory
{
    protected $model = AgentMessage::class;

    public function definition(): array
    {
        return [
            'sender_id' => Agent::factory(),
            'recipient_id' => Agent::factory(),
            'channel' => fake()->randomElement(['swarm.general', 'task.updates', 'agent.direct']),
            'type' => fake()->randomElement(['message', 'command', 'event', 'alert']),
            'payload' => ['content' => fake()->sentence()],
            'status' => 'pending',
            'delivered_at' => null,
            'read_at' => null,
        ];
    }
}