<?php

namespace Tests\Unit\Services\Analytics;

use App\Models\Task;
use App\Models\TaskResult;
use App\Services\Analytics\MetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsCollectorTest extends TestCase
{
    use RefreshDatabase;

    private MetricsCollector $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metrics = app(MetricsCollector::class);
    }

    public function test_summary_returns_correct_counts(): void
    {
        TaskResult::factory()->count(5)->create(['status' => 'completed']);
        TaskResult::factory()->count(2)->create(['status' => 'failed']);

        $summary = $this->metrics->summary();

        $this->assertEquals(7, $summary['total_executions']);
        $this->assertEquals(5, $summary['completed']);
        $this->assertEquals(2, $summary['failed']);
        $this->assertEquals(71.43, $summary['success_rate']);
    }

    public function test_summary_returns_zero_for_empty_database(): void
    {
        $summary = $this->metrics->summary();

        $this->assertEquals(0, $summary['total_executions']);
        $this->assertEquals(0, $summary['success_rate']);
    }

    public function test_status_distribution_returns_counts_by_status(): void
    {
        TaskResult::factory()->count(3)->create(['status' => 'completed']);
        TaskResult::factory()->count(1)->create(['status' => 'failed']);
        TaskResult::factory()->count(1)->create(['status' => 'pending']);

        $distribution = $this->metrics->statusDistribution();

        $this->assertEquals(3, $distribution['completed']);
        $this->assertEquals(1, $distribution['failed']);
        $this->assertEquals(1, $distribution['pending']);
    }

    public function test_daily_trends_returns_last_30_days(): void
    {
        TaskResult::factory()->create(['status' => 'completed', 'created_at' => now()->subDays(2)]);
        TaskResult::factory()->create(['status' => 'failed', 'created_at' => now()->subDays(2)]);
        TaskResult::factory()->create(['status' => 'completed', 'created_at' => now()->subDays(1)]);

        $trends = $this->metrics->dailyTrends(7);

        $this->assertIsArray($trends);
        $this->assertNotEmpty($trends);

        $twoDaysAgo = collect($trends)->first(fn ($d) => $d['date'] === now()->subDays(2)->toDateString());
        $this->assertNotNull($twoDaysAgo);
        $this->assertEquals(1, $twoDaysAgo['completed']);
        $this->assertEquals(1, $twoDaysAgo['failed']);
    }

    public function test_for_task_returns_task_breakdown(): void
    {
        $task = Task::factory()->create(['title' => 'Task A']);
        TaskResult::factory()->count(2)->create(['task_id' => $task->id, 'status' => 'completed', 'duration_ms' => 100]);
        TaskResult::factory()->create(['task_id' => $task->id, 'status' => 'failed', 'duration_ms' => 50]);

        $report = $this->metrics->forWorkflow($task->id);

        $this->assertEquals($task->title, $report['task_name']);
        $this->assertEquals(3, $report['total_executions']);
        $this->assertEquals(66.67, $report['success_rate']);
    }

    public function test_for_driver_returns_driver_metrics(): void
    {
        $task = Task::factory()->create(['driver' => 'openai']);
        TaskResult::factory()->count(4)->create(['task_id' => $task->id, 'status' => 'completed']);
        TaskResult::factory()->create(['task_id' => $task->id, 'status' => 'failed', 'error_message' => 'Rate limit']);

        $report = $this->metrics->forDriver('openai');

        $this->assertEquals('openai', $report['driver']);
        $this->assertEquals(5, $report['total_executions']);
        $this->assertEquals(80, $report['success_rate']);
        $this->assertArrayHasKey('Rate limit', $report['top_errors']);
    }
}