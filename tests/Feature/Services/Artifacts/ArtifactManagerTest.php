<?php
// tests/Feature/Services/Artifacts/ArtifactManagerTest.php

namespace Tests\Feature\Services\Artifacts;

use App\Models\TaskResult;
use App\Services\Artifacts\ArtifactManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtifactManagerTest extends TestCase
{
    use RefreshDatabase;

    private ArtifactManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->manager = new ArtifactManager('local', 'artifacts');
    }

    public function test_store_uploaded_file(): void
    {
        $result = TaskResult::factory()->create();
        $file = UploadedFile::fake()->createWithContent('data.json', '{"key": "value"}');

        $artifact = $this->manager->store($result->id, $file, 'my-data.json');

        $this->assertDatabaseHas('task_artifacts', [
            'task_result_id' => $result->id,
            'name' => 'my-data.json',
            'type' => 'json',
        ]);
        Storage::disk('local')->assertExists($artifact->path);
    }

    public function test_store_raw_content(): void
    {
        $result = TaskResult::factory()->create();

        $artifact = $this->manager->storeRaw($result->id, '{"test": true}', 'report.json', 'json');

        $this->assertDatabaseHas('task_artifacts', [
            'task_result_id' => $result->id,
            'name' => 'report.json',
        ]);
        $this->assertEquals('{"test": true}', Storage::disk('local')->get($artifact->path));
    }

    public function test_retrieve_artifact(): void
    {
        $result = TaskResult::factory()->create();
        $stored = $this->manager->storeRaw($result->id, 'content', 'file.txt', 'text');

        $retrieved = $this->manager->retrieve($stored->id);

        $this->assertNotNull($retrieved);
        $this->assertEquals($stored->id, $retrieved->id);
    }

    public function test_download_artifact_content(): void
    {
        $result = TaskResult::factory()->create();
        $stored = $this->manager->storeRaw($result->id, 'downloadable content', 'file.txt', 'text');

        $content = $this->manager->download($stored->id);

        $this->assertEquals('downloadable content', $content);
    }

    public function test_delete_artifact_removes_file_and_record(): void
    {
        $result = TaskResult::factory()->create();
        $stored = $this->manager->storeRaw($result->id, 'temp', 'temp.txt', 'text');

        $deleted = $this->manager->delete($stored->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('task_artifacts', ['id' => $stored->id]);
        Storage::disk('local')->assertMissing($stored->path);
    }

    public function test_list_for_result(): void
    {
        $result = TaskResult::factory()->create();
        $this->manager->storeRaw($result->id, 'a', 'a.json', 'json');
        $this->manager->storeRaw($result->id, 'b', 'b.csv', 'csv');

        $artifacts = $this->manager->listForResult($result->id);

        $this->assertCount(2, $artifacts);
    }
}