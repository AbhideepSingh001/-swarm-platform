<?php

namespace Tests\Unit\Services\Swarm;

use App\Services\Swarm\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    protected CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->breaker = new CircuitBreaker();
        Cache::flush();
    }

    /** @test */
    public function it_starts_closed(): void
    {
        $this->assertFalse($this->breaker->isOpen('agent-1'));
    }

    /** @test */
    public function it_trips_after_threshold_failures(): void
    {
        config(['swarm.circuit_breaker.threshold' => 3]);

        $this->breaker->recordFailure('agent-1');
        $this->breaker->recordFailure('agent-1');

        $this->assertFalse($this->breaker->isOpen('agent-1'));

        $this->breaker->recordFailure('agent-1');

        $this->assertTrue($this->breaker->isOpen('agent-1'));
    }

    /** @test */
    public function it_resets_on_success(): void
    {
        config(['swarm.circuit_breaker.threshold' => 2]);

        $this->breaker->recordFailure('agent-1');
        $this->breaker->recordFailure('agent-1');
        $this->assertTrue($this->breaker->isOpen('agent-1'));

        $this->breaker->recordSuccess('agent-1');
        $this->assertFalse($this->breaker->isOpen('agent-1'));
    }

    /** @test */
    public function it_tracks_failure_count(): void
    {
        $this->breaker->recordFailure('agent-1');
        $this->breaker->recordFailure('agent-1');

        $status = $this->breaker->status('agent-1');

        $this->assertEquals(2, $status['failure_count']);
        $this->assertFalse($status['is_open']);
    }

    /** @test */
    public function manual_reset_clears_state(): void
    {
        config(['swarm.circuit_breaker.threshold' => 1]);
        $this->breaker->recordFailure('agent-1');
        $this->assertTrue($this->breaker->isOpen('agent-1'));

        $this->breaker->reset('agent-1');
        $this->assertFalse($this->breaker->isOpen('agent-1'));
        $this->assertEquals(0, $this->breaker->status('agent-1')['failure_count']);
    }
}
