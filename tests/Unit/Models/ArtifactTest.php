<?php
// tests/Unit/Models/ArtifactTest.php

namespace Tests\Unit\Models;

use App\Models\Artifact;
use App\Models\TaskResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtifactTest extends TestCase
{
    use RefreshDatabase;

    public function test_artifact_belongs_to_task_result(): void
    {
        $result = TaskResult::factory()->create();
        $artifact = Artifact::factory()->create(['task_result_id' => $result->id]);

        $this->assertInstanceOf(TaskResult::class, $artifact->taskResult);
    }

    public function test_artifact_generates_url_attribute(): void
    {
        Storage::fake('local');
        $artifact = Artifact::factory()->create(['disk' => 'local', 'path' => 'test/file.json']);

        $this->assertStringContainsString('test/file.json', $artifact->url);
    }

    public function test_artifact_deletes_file_on_model_deletion(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('artifacts/test.json', '{"test": true}');

        $artifact = Artifact::factory()->create([
            'disk' => 'local',
            'path' => 'artifacts/test.json',
        ]);

        $artifact->delete();

        Storage::disk('local')->assertMissing('artifacts/test.json');
    }
}