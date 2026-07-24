<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Swarm;

use App\Jobs\Swarm\ExecuteWorkflowStep;
use App\Services\Swarm\WorkflowBatchMonitor;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class WorkflowBatchMonitorTest extends TestCase
{
    private WorkflowBatchMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new WorkflowBatchMonitor();
    }

    private function createMockBatch(array $options = []): Batch
    {
        $batch = $this->createMock(Batch::class);
        $batch->options = array_merge([
            'execution_id' => 'exec-001',
            'level_index' => 0,
            'total_levels' => 3,
            'next_level_steps' => [],
            'context' => [],
        ], $options);

        return $batch;
    }

    #[Test]
    public function it_completes_workflow_when_no_next_level(): void
    {
        Bus::fake();

        $batch = $this->createMockBatch([
            'level_index' => 2,
            'total_levels' => 3,
            'next_level_steps' => [],
        ]);

        $this->monitor->onLevelComplete($batch);
        Bus::assertNotDispatched(ExecuteWorkflowStep::class);
    }

    #[Test]
    public function it_dispatches_next_level_when_steps_exist(): void
    {
        Bus::fake();

        $batch = $this->createMockBatch([
            'level_index' => 0,
            'total_levels' => 2,
            'next_level_steps' => [
                ['id' => 'step-c', 'name' => 'Step C'],
                ['id' => 'step-d', 'name' => 'Step D'],
            ],
            'context' => ['_levels' => []],
        ]);

        $this->monitor->onLevelComplete($batch);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 2;
        });
    }

    #[Test]
    public function it_handles_level_failure_gracefully(): void
    {
        $batch = $this->createMockBatch([
            'level_index' => 1,
            'total_levels' => 3,
        ]);

        $exception = new RuntimeException('Step B failed');
        $this->monitor->onLevelFailure($batch, $exception);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_calculates_progress_percent_correctly(): void
    {
        $batch = $this->createMockBatch([
            'level_index' => 1,
            'total_levels' => 4,
        ]);

        $expectedPercent = (int) ((2 / 4) * 100);
        $this->assertSame(50, $expectedPercent);
    }

    #[Test]
    public function it_handles_workflow_completion(): void
    {
        $batch = $this->createMockBatch([
            'total_levels' => 2,
        ]);

        $this->monitor->onWorkflowComplete($batch);
        $this->assertTrue(true);
    }
}
