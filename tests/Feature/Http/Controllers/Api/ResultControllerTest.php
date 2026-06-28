<?php
// tests/Feature/Http/Controllers/Api/ResultControllerTest.php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Task;
use App\Models\TaskExecutionLog;
use App\Models\TaskResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResultControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_results(): void
    {
        $task = Task::factory()->create();
        TaskResult::factory()->count(5)->create(['task_id' => $task->id]);

        $response = $this->getJson("/api/results?task_id={$task->id}&per_page=3");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3)  // Laravel returns as int
            ->assertJsonPath('meta.total', 5);
    }

    public function test_show_returns_result_with_relations(): void
    {
        $result = TaskResult::factory()->create();
        TaskExecutionLog::factory()->create(['task_result_id' => $result->id]);

        $response = $this->getJson("/api/results/{$result->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $result->id)
            ->assertJsonPath('data.status', $result->status);
    }

    public function test_show_returns_404_for_missing_result(): void
    {
        $response = $this->getJson('/api/results/99999');

        $response->assertNotFound()
            ->assertJsonPath('message', 'Result not found.');  // No period
    }

    public function test_store_creates_new_result(): void
    {
        $task = Task::factory()->create();

        $response = $this->postJson('/api/results', [
            'task_id' => $task->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.task_id', $task->id)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_update_modifies_result(): void
    {
        $result = TaskResult::factory()->create(['status' => 'pending']);

        $response = $this->putJson("/api/results/{$result->id}", [
            'status' => 'running',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'running');
    }

    public function test_delete_removes_result(): void
    {
        $result = TaskResult::factory()->create();

        $response = $this->deleteJson("/api/results/{$result->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('task_results', ['id' => $result->id]);
    }

    public function test_logs_returns_execution_logs(): void
    {
        $result = TaskResult::factory()->create();
        TaskExecutionLog::factory()->count(3)->create(['task_result_id' => $result->id]);

        $response = $this->getJson("/api/results/{$result->id}/logs");

        $response->assertOk()->assertJsonCount(3, 'data.data');
    }

    public function test_store_artifact_uploads_file(): void
    {
        Storage::fake('local');
        $result = TaskResult::factory()->create();
        $file = UploadedFile::fake()->createWithContent('report.json', '{}');

        $response = $this->postJson("/api/results/{$result->id}/artifacts", [
            'file' => $file,
            'name' => 'my-report.json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'my-report.json');
        $this->assertDatabaseHas('task_artifacts', ['task_result_id' => $result->id]);
    }
}