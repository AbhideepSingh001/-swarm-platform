<?php
// database/factories/ArtifactFactory.php

namespace Database\Factories;

use App\Models\Artifact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArtifactFactory extends Factory
{
    protected $model = Artifact::class;

    public function definition(): array
    {
        return [
            'task_result_id' => \App\Models\TaskResult::factory(),
            'name' => $this->faker->word() . '.json',
            'type' => 'json',
            'mime_type' => 'application/json',
            'disk' => 'local',
            'path' => 'artifacts/2026/06/28/' . $this->faker->uuid() . '.json',
            'size_bytes' => $this->faker->numberBetween(100, 10000),
            'metadata' => null,
        ];
    }
}