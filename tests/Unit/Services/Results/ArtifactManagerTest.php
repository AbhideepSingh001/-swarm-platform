<?php

namespace Tests\Unit\Services\Artifacts;

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
        $this->manager = app(ArtifactManager::class);
        Storage::fake('local');
    }

    public function test_store_file_creates_artifact(): void
    {
        $result = TaskResult::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $artifact = $this->manager->store($result->id, $file, 'My Document');

        $this->assertEquals('My Document', $artifact->name);
        $this->assertEquals('binary', $artifact->type);
        $this->assertEquals(100 * 1024, $artifact->size_bytes);
        Storage::disk('local')->assertExists($artifact->path);
        $this->assertDatabaseHas('task_artifacts', ['id' => $artifact->id]);
    }

    public function test_store_json_creates_artifact(): void
    {
        $result = TaskResult::factory()->create();
        $data = ['key' => 'value', 'nested' => ['a' => 1]];

        $artifact = $this->manager->storeRaw($result->id, json_encode($data, JSON_PRETTY_PRINT), 'Response Data', 'json');

        $this->assertEquals('Response Data', $artifact->name);
        $this->assertEquals('json', $artifact->type);
        $this->assertEquals('application/json', $artifact->mime_type);
        $this->assertJsonStringEqualsJsonString(
            json_encode($data, JSON_PRETTY_PRINT),
            Storage::disk('local')->get($artifact->path)
        );
        $this->assertDatabaseHas('task_artifacts', ['id' => $artifact->id]);
    }

    public function test_store_text_creates_artifact(): void
    {
        $result = TaskResult::factory()->create();
        $content = "Line 1\nLine 2\nLine 3";

        $artifact = $this->manager->storeRaw($result->id, $content, 'Log Output', 'text');

        $this->assertEquals('Log Output', $artifact->name);
        $this->assertEquals('text', $artifact->type);
        $this->assertEquals('text/plain', $artifact->mime_type);
        $this->assertEquals($content, Storage::disk('local')->get($artifact->path));
        $this->assertDatabaseHas('task_artifacts', ['id' => $artifact->id]);
    }

    public function test_delete_removes_artifact(): void
    {
        $result = TaskResult::factory()->create();
        $artifact = $this->manager->storeRaw($result->id, 'content', 'test', 'text');

        $this->assertTrue($this->manager->delete($artifact->id));
        $this->assertDatabaseMissing('task_artifacts', ['id' => $artifact->id]);
    }

    public function test_get_disk_returns_disk(): void
    {
        $manager = app(ArtifactManager::class);
        $disk = $manager->getDisk();

        $this->assertEquals('local', $disk);
    }
}