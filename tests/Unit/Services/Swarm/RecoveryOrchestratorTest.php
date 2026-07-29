<?php

namespace Tests\Unit\Services\Swarm;

use App\Models\DeadLetter;
use App\Services\Swarm\CircuitBreaker;
use App\Services\Swarm\DeadLetterQueue;
use App\Services\Swarm\RecoveryOrchestrator;
use App\Services\Swarm\WorkflowResumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RecoveryOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected RecoveryOrchestrator $orchestrator;
    protected CircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->circuitBreaker = new CircuitBreaker();
        $this->orchestrator = new RecoveryOrchestrator(
            new DeadLetterQueue(),
            $this->circuitBreaker,
            $this->createMock(WorkflowResumer::class),
        );
    }

    /** @test */
    public function it_retries_an_open_dead_letter(): void
    {
        // Use a simple mock job class for testing
        config(['swarm.job_class' => \Tests\Fixtures\MockRetryJob::class]);

        $deadLetter = DeadLetter::factory()->create(['status' => 'open']);

        $result = $this->orchestrator->retry($deadLetter);

        $this->assertTrue($result['success']);
        $this->assertEquals('resolved', $deadLetter->fresh()->status);
    }

    /** @test */
    public function it_blocks_retry_when_circuit_is_open(): void
    {
        config(['swarm.circuit_breaker.threshold' => 1]);

        $deadLetter = DeadLetter::factory()->create([
            'status' => 'open',
            'agent_id' => 'agent-faulty',
        ]);

        $this->circuitBreaker->recordFailure('agent-faulty');
        $this->circuitBreaker->recordFailure('agent-faulty');

        $result = $this->orchestrator->retry($deadLetter);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Circuit breaker is open', $result['message']);
    }

    /** @test */
    public function it_skips_a_step_and_resumes_workflow(): void
    {
        $resumerMock = $this->createMock(WorkflowResumer::class);
        $resumerMock->expects($this->once())
            ->method('resumeWithSkippedStep')
            ->with('exec-123', 'step-1', []);

        $orchestrator = new RecoveryOrchestrator(
            new DeadLetterQueue(),
            $this->circuitBreaker,
            $resumerMock,
        );

        $deadLetter = DeadLetter::factory()->create([
            'status' => 'open',
            'execution_id' => 'exec-123',
            'step_id' => 'step-1',
            'context' => [],
        ]);

        $result = $orchestrator->skip($deadLetter, 'manual skip');

        $this->assertTrue($result['success']);
        $this->assertEquals('resolved', $deadLetter->fresh()->status);
        $this->assertEquals('manual skip', $deadLetter->fresh()->resolution);
    }

    /** @test */
    public function it_reassigns_to_a_new_agent(): void
    {
        config(['swarm.job_class' => \Tests\Fixtures\MockRetryJob::class]);

        $deadLetter = DeadLetter::factory()->create([
            'status' => 'open',
            'agent_id' => 'agent-old',
        ]);

        $result = $this->orchestrator->reassign($deadLetter, 'agent-new');

        $this->assertTrue($result['success']);
        $this->assertEquals('resolved', $deadLetter->fresh()->status);
    }

    /** @test */
    public function it_prevents_actions_on_non_open_dead_letters(): void
    {
        $deadLetter = DeadLetter::factory()->create(['status' => 'resolved']);

        $result = $this->orchestrator->retry($deadLetter);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not in open status', $result['message']);
    }
}
