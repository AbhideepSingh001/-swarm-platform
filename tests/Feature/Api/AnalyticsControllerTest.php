<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\TaskResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_metrics(): void
    {
        TaskResult::factory()->count(3)->create(['status' => 'completed']);
        TaskResult::factory()->create(['status' => 'failed']);

        $response = $this->getJson('/api/analytics/summary');

        $response->assertOk()
            ->assertJsonPath('data.total_executions', 4)
            ->assertJsonPath('data.completed', 3)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.success_rate', 75.0);
    }

    public function test_status_distribution_returns_counts(): void
    {
        TaskResult::factory()->count(2)->create(['status' => 'completed']);
        TaskResult::factory()->create(['status' => 'failed']);

        $response = $this->getJson('/api/analytics/status-distribution');

        $response->assertOk()
            ->assertJsonPath('data.completed', 2)
            ->assertJsonPath('data.failed', 1);
    }

    public function test_daily_trends_returns_trend_data(): void
    {
        TaskResult::factory()->create(['status' => 'completed', 'created_at' => now()->subDay()]);

        $response = $this->getJson('/api/analytics/daily-trends?days=7');

        $response->assertOk()->assertJsonStructure(['data' => [['date', 'completed', 'failed', 'pending', 'running']]]);
    }

    public function test_task_returns_task_analytics(): void
    {
        $task = Task::factory()->create();
        TaskResult::factory()->count(2)->create(['task_id' => $task->id, 'status' => 'completed']);

        $response = $this->getJson("/api/analytics/task/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('data.task_id', $task->id)
            ->assertJsonPath('data.total_executions', 2)
            ->assertJsonPath('data.success_rate', 100.0);
    }

    public function test_driver_returns_driver_analytics(): void
    {
        $task = Task::factory()->create(['driver' => 'openai']);
        TaskResult::factory()->count(3)->create(['task_id' => $task->id, 'status' => 'completed']);

        $response = $this->getJson('/api/analytics/driver/openai');

        $response->assertOk()
            ->assertJsonPath('data.driver', 'openai')
            ->assertJsonPath('data.total_executions', 3)
            ->assertJsonPath('data.success_rate', 100.0);
    }
}