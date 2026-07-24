<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Swarm;

use App\Services\Swarm\AsyncSwarmRunner;
use App\Services\Swarm\WorkflowBatchMonitor;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class AsyncSwarmRunnerTest extends TestCase
{
    private AsyncSwarmRunner $runner;
    private WorkflowBatchMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new WorkflowBatchMonitor();
        $this->runner = new AsyncSwarmRunner($this->monitor);
    }

    #[Test]
    public function it_resolves_linear_dag_into_single_level(): void
    {
        $workflow = [
            'name' => 'Linear',
            'steps' => [
                ['id' => 'a', 'name' => 'Step A'],
                ['id' => 'b', 'name' => 'Step B'],
            ],
            'edges' => [],
        ];

        $levels = $this->runner->resolveDagLevels($workflow);

        $this->assertCount(1, $levels);
        $this->assertCount(2, $levels[0]);
    }

    #[Test]
    public function it_resolves_dependent_steps_into_multiple_levels(): void
    {
        $workflow = [
            'name' => 'Two Levels',
            'steps' => [
                ['id' => 'a', 'name' => 'Step A'],
                ['id' => 'b', 'name' => 'Step B'],
                ['id' => 'c', 'name' => 'Step C'],
            ],
            'edges' => [
                ['from' => 'a', 'to' => 'c'],
                ['from' => 'b', 'to' => 'c'],
            ],
        ];

        $levels = $this->runner->resolveDagLevels($workflow);

        $this->assertCount(2, $levels);
        $this->assertCount(2, $levels[0]);
        $this->assertCount(1, $levels[1]);
        $this->assertSame('c', $levels[1][0]['id']);
    }

    #[Test]
    public function it_resolves_three_level_dag(): void
    {
        $workflow = [
            'name' => 'Three Levels',
            'steps' => [
                ['id' => 'a', 'name' => 'A'],
                ['id' => 'b', 'name' => 'B'],
                ['id' => 'c', 'name' => 'C'],
                ['id' => 'd', 'name' => 'D'],
            ],
            'edges' => [
                ['from' => 'a', 'to' => 'b'],
                ['from' => 'b', 'to' => 'c'],
                ['from' => 'c', 'to' => 'd'],
            ],
        ];

        $levels = $this->runner->resolveDagLevels($workflow);

        $this->assertCount(4, $levels);
        $this->assertSame('a', $levels[0][0]['id']);
        $this->assertSame('b', $levels[1][0]['id']);
        $this->assertSame('c', $levels[2][0]['id']);
        $this->assertSame('d', $levels[3][0]['id']);
    }

    #[Test]
    public function it_throws_on_cyclic_dag(): void
    {
        $workflow = [
            'name' => 'Cyclic',
            'steps' => [
                ['id' => 'a', 'name' => 'A'],
                ['id' => 'b', 'name' => 'B'],
            ],
            'edges' => [
                ['from' => 'a', 'to' => 'b'],
                ['from' => 'b', 'to' => 'a'],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cycle detected');

        $this->runner->resolveDagLevels($workflow);
    }

    #[Test]
    public function it_returns_empty_levels_for_empty_workflow(): void
    {
        $levels = $this->runner->resolveDagLevels(['steps' => []]);
        $this->assertEmpty($levels);
    }

    #[Test]
    public function it_dispatches_batch_for_workflow(): void
    {
        Bus::fake();

        $workflow = [
            'name' => 'Test Dispatch',
            'steps' => [
                ['id' => 's1', 'name' => 'Step 1', 'agent_id' => 'agent-1'],
                ['id' => 's2', 'name' => 'Step 2', 'agent_id' => 'agent-2'],
            ],
            'edges' => [],
        ];

        $result = $this->runner->dispatch('wf-001', $workflow, ['query' => 'test']);

        $this->assertArrayHasKey('execution_id', $result);
        $this->assertSame('queued', $result['status']);
        $this->assertNotNull($result['batch_id']);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 2;
        });
    }

    #[Test]
    public function it_completes_immediately_for_empty_workflow(): void
    {
        $workflow = ['name' => 'Empty', 'steps' => []];

        $result = $this->runner->dispatch('wf-empty', $workflow);

        $this->assertSame('completed', $result['status']);
        $this->assertNull($result['batch_id']);
    }
}   
