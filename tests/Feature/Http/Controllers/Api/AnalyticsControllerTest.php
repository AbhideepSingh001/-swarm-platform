<?php
// tests/Feature/Http/Controllers/Api/AnalyticsControllerTest.php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Task;
use App\Models\TaskResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_metrics_returns_aggregated_data(): void
    {
        $task = Task::factory()->create();
        TaskResult::factory()->completed()->count(4)->create(['task_id' => $task->id]);
        TaskResult::factory()->failed()->count(1)->create(['task_id' => $task->id]);

        $response = $this->getJson("/api/analytics/tasks?task_id={$task->id}");

        $response->assertOk()
            ->assertJsonPath('data.total_executions', 5)
            ->assertJsonPath('data.successful', 4)
            ->assertJsonPath('data.success_rate', 80.0);
    }

    public function test_dashboard_summary_returns_overview(): void
    {
        TaskResult::factory()->completed()->count(3)->create();
        TaskResult::factory()->failed()->count(1)->create();

        $response = $this->getJson('/api/analytics/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.total_executions', 4)
            ->assertJsonPath('data.success_rate', 75.0);
    }

    public function test_time_series_returns_grouped_executions(): void
    {
        TaskResult::factory()->completed()->create(['created_at' => now()->subDay()]);
        TaskResult::factory()->completed()->create(['created_at' => now()]);

        $response = $this->getJson('/api/analytics/time-series?metric=executions&group_by=day');

        $response->assertOk()
            ->assertJsonPath('data.metric', 'executions')
            ->assertJsonStructure([
    'data' => [
        'metric',
        'group_by',
        'data' => [
            '*' => ['period', 'executions', 'successful', 'failed']
        ]
    ]
]);
    }

    public function test_driver_metrics_returns_driver_stats(): void
    {
        TaskResult::factory()->completed()->count(2)->create([
            'metadata' => ['driver' => 'openai', 'tokens' => ['total' => 100]],
        ]);

        $response = $this->getJson('/api/analytics/drivers?driver=openai');

        $response->assertOk()
            ->assertJsonPath('data.driver', 'openai')
            ->assertJsonPath('data.total_executions', 2);
    }

    // REMOVED: test_workflow_trend — WorkflowExecution model doesn't exist
}