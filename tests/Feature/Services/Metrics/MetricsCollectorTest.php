<?php

namespace Tests\Feature\Services\Metrics;

use App\Models\Agent;
use App\Models\Task;
use App\Models\TaskResult;
use App\Services\Metrics\Aggregators\DriverAggregator;
use App\Services\Metrics\Aggregators\WorkflowAggregator;
use App\Services\Metrics\MetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsCollectorTest extends TestCase
{
    use RefreshDatabase;

    private MetricsCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new MetricsCollector(new DriverAggregator(), new WorkflowAggregator());
    }

    public function test_collect_for_driver_returns_structured_metrics(): void
    {
        $metrics = $this->collector->collectForDriver('openai', 1, [
            'tokens' => ['prompt' => 100, 'completion' => 50],
            'latency_ms' => 1200,
            'model' => 'gpt-4',
        ]);

        $this->assertEquals('openai', $metrics['driver']);
        $this->assertEquals(150, $metrics['tokens']['total']);
        $this->assertEquals(1200, $metrics['latency_ms']);
    }

    public function test_aggregate_by_task_returns_summary(): void
    {
        $task = Task::factory()->create();
        TaskResult::factory()->completed()->count(3)->create(['task_id' => $task->id]);
        TaskResult::factory()->failed()->count(1)->create(['task_id' => $task->id]);

        $summary = $this->collector->aggregateByTask($task->id);

        $this->assertEquals(4, $summary['total_executions']);
        $this->assertEquals(3, $summary['successful']);
        $this->assertEquals(75.0, $summary['success_rate']);
    }

    public function test_aggregate_by_agent_returns_summary(): void
    {
        $agent = Agent::factory()->create();
        TaskResult::factory()->completed()->count(2)->create(['agent_id' => $agent->id]);

        $summary = $this->collector->aggregateByAgent($agent->id);

        $this->assertEquals(2, $summary['total_executions']);
        $this->assertEquals(2, $summary['successful']);
    }

    public function test_time_series_returns_grouped_data(): void
    {
        TaskResult::factory()->completed()->create(['created_at' => now()->subDay()]);
        TaskResult::factory()->completed()->create(['created_at' => now()]);

        $series = $this->collector->getTimeSeries('executions', ['group_by' => 'day']);

        $this->assertEquals('executions', $series['metric']);
        $this->assertNotEmpty($series['data']);
        $this->assertArrayHasKey('period', $series['data'][0]);
        $this->assertArrayHasKey('executions', $series['data'][0]);
    }
}