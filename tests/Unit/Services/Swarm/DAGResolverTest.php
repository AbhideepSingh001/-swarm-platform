<?php

namespace Tests\Unit\Services\Swarm;

use App\Services\Swarm\DAGResolver;
use PHPUnit\Framework\TestCase;

class DAGResolverTest extends TestCase
{
    private DAGResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DAGResolver();
    }

    /** @test */
    public function it_resolves_simple_linear_dependencies(): void
    {
        $graph = [
            'a' => [],
            'b' => ['a'],
            'c' => ['b'],
        ];

        $sorted = $this->resolver->resolve($graph);

        $this->assertEquals(['a', 'b', 'c'], $sorted);
    }

    /** @test */
    public function it_resolves_diamond_dependencies(): void
    {
        $graph = [
            'a' => [],
            'b' => ['a'],
            'c' => ['a'],
            'd' => ['b', 'c'],
        ];

        $sorted = $this->resolver->resolve($graph);

        $this->assertEquals('a', $sorted[0]);
        $this->assertEquals('d', $sorted[3]);
        $this->assertContains('b', array_slice($sorted, 1, 2));
        $this->assertContains('c', array_slice($sorted, 1, 2));
    }

    /** @test */
    public function it_detects_cycles(): void
    {
        $graph = [
            'a' => ['b'],
            'b' => ['c'],
            'c' => ['a'],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cycle detected');

        $this->resolver->resolve($graph);
    }

    /** @test */
    public function it_returns_true_when_cycle_exists(): void
    {
        $graph = [
            'a' => ['b'],
            'b' => ['a'],
        ];

        $this->assertTrue($this->resolver->hasCycle($graph));
    }

    /** @test */
    public function it_returns_false_when_no_cycle(): void
    {
        $graph = [
            'a' => [],
            'b' => ['a'],
        ];

        $this->assertFalse($this->resolver->hasCycle($graph));
    }

    /** @test */
    public function it_finds_cycle_nodes(): void
    {
        $graph = [
            'a' => ['b'],
            'b' => ['c'],
            'c' => ['a'],
        ];

        $cycle = $this->resolver->findCycle($graph);

        $this->assertNotEmpty($cycle);
        $this->assertContains('a', $cycle);
        $this->assertContains('b', $cycle);
        $this->assertContains('c', $cycle);
    }

    /** @test */
    public function it_generates_execution_levels_for_parallel_steps(): void
    {
        $graph = [
            'research' => [],
            'outline' => [],
            'draft' => ['research', 'outline'],
            'review' => ['draft'],
            'publish' => ['review'],
        ];

        $levels = $this->resolver->getExecutionLevels($graph);

        $this->assertCount(4, $levels);
        $this->assertEquals(['outline', 'research'], $levels[0]->sort()->values()->all());
        $this->assertEquals(['draft'], $levels[1]->all());
        $this->assertEquals(['review'], $levels[2]->all());
        $this->assertEquals(['publish'], $levels[3]->all());
    }

    /** @test */
    public function it_generates_levels_for_complex_dag(): void
    {
        $graph = [
            'a' => [],
            'b' => [],
            'c' => ['a'],
            'd' => ['a', 'b'],
            'e' => ['c', 'd'],
        ];

        $levels = $this->resolver->getExecutionLevels($graph);

        // Level 0: a, b (no deps)
        // Level 1: c, d (c needs a, d needs a+b — both ready)
        // Level 2: e (needs c+d)
        $this->assertCount(3, $levels);
        $this->assertEquals(['a', 'b'], $levels[0]->sort()->values()->all());
        $this->assertEquals(['c', 'd'], $levels[1]->sort()->values()->all());
        $this->assertEquals(['e'], $levels[2]->all());
    }

    /** @test */
    public function it_handles_single_node(): void
    {
        $graph = ['solo' => []];

        $sorted = $this->resolver->resolve($graph);

        $this->assertEquals(['solo'], $sorted);
    }

    /** @test */
    public function it_handles_empty_graph(): void
    {
        $sorted = $this->resolver->resolve([]);

        $this->assertEquals([], $sorted);
    }

    /** @test */
    public function it_throws_on_self_reference(): void
    {
        $graph = ['a' => ['a']];

        $this->expectException(\RuntimeException::class);
        $this->resolver->resolve($graph);
    }
}