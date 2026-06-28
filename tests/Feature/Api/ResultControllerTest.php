<?php

namespace Tests\Feature\Api;

use App\Models\Artifact;
use App\Models\Task;
use App\Models\TaskExecutionLog;
use App\Models\TaskResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_results(): void
    {
        TaskResult::factory()->count(5)->create();

        $response = $this->getJson('/api/results');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'status', 'task_name']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        TaskResult::factory()->count(3)->create(['status' => 'completed']);
        TaskResult::factory()->create(['status' => 'failed']);

        $response = $this->getJson('/api/results?status=completed');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_show_returns_single_result(): void
    {
        $result = TaskResult::factory()->create();

        $response = $this->getJson("/api/results/{$result->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $result->id)
            ->assertJsonPath('data.status', $result->status);
    }

    public function test_show_returns_404_for_missing_result(): void
    {
        $response = $this->getJson('/api/results/99999');

        $response->assertNotFound()
            ->assertJsonPath('message', 'Result not found.');
    }

    public function test_by_task_returns_latest_result(): void
    {
        $task = Task::factory()->create();
        TaskResult::factory()->create(['task_id' => $task->id, 'created_at' => now()->subDay()]);
        $latest = TaskResult::factory()->create(['task_id' => $task->id]);

        $response = $this->getJson("/api/results/task/{$task->id}");

        $response->assertOk()->assertJsonPath('data.id', $latest->id);
    }

    public function test_logs_returns_paginated_logs(): void
    {
        $result = TaskResult::factory()->create();
        TaskExecutionLog::factory()->count(5)->create(['task_result_id' => $result->id]);

        $response = $this->getJson("/api/results/{$result->id}/logs");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['data' => [['id', 'level', 'message']]]]);
    }

    public function test_artifacts_returns_list(): void
    {
        $result = TaskResult::factory()->create();
        Artifact::factory()->count(2)->create(['task_result_id' => $result->id]);

        $response = $this->getJson("/api/results/{$result->id}/artifacts");

        $response->assertOk()->assertJsonCount(2, 'data');
    }
}