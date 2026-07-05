<?php

namespace Tests\Feature\Services\Swarm;

use App\Models\WorkflowExecution;
use App\Services\Swarm\WorkflowStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new WorkflowStateMachine();
    }

    private function createExecution(string $status = 'pending'): WorkflowExecution
    {
        return WorkflowExecution::factory()->create(['status' => $status]);
    }

    /** @test */
    public function it_allows_pending_to_running(): void
    {
        $execution = $this->createExecution('pending');

        $this->assertTrue($this->stateMachine->canTransition($execution, 'running'));
    }

    /** @test */
    public function it_allows_pending_to_cancelled(): void
    {
        $execution = $this->createExecution('pending');

        $this->assertTrue($this->stateMachine->canTransition($execution, 'cancelled'));
    }

    /** @test */
    public function it_disallows_pending_to_completed(): void
    {
        $execution = $this->createExecution('pending');

        $this->assertFalse($this->stateMachine->canTransition($execution, 'completed'));
    }

    /** @test */
    public function it_allows_running_to_paused(): void
    {
        $execution = $this->createExecution('running');

        $this->assertTrue($this->stateMachine->canTransition($execution, 'paused'));
    }

    /** @test */
    public function it_allows_running_to_completed(): void
    {
        $execution = $this->createExecution('running');

        $this->assertTrue($this->stateMachine->canTransition($execution, 'completed'));
    }

    /** @test */
    public function it_allows_running_to_failed(): void
    {
        $execution = $this->createExecution('running');

        $this->assertTrue($this->stateMachine->canTransition($execution, 'failed'));
    }

    /** @test */
    public function it_allows_running_to_cancelled(): void
    {
        $execution = $this->createExecution('running');

        $this->assertTrue($this->stateMachine->canTransition($execution, 'cancelled'));
    }

    /** @test */
    public function it_allows_paused_to_running(): void
    {
        $execution = $this->createExecution('paused');

        $this->assertTrue($this->stateMachine->canTransition($execution, 'running'));
    }

    /** @test */
    public function it_allows_paused_to_cancelled(): void
    {
        $execution = $this->createExecution('paused');

        $this->assertTrue($this->stateMachine->canTransition($execution, 'cancelled'));
    }

    /** @test */
    public function it_disallows_paused_to_completed(): void
    {
        $execution = $this->createExecution('paused');

        $this->assertFalse($this->stateMachine->canTransition($execution, 'completed'));
    }

    /** @test */
    public function it_disallows_any_transition_from_completed(): void
    {
        $execution = $this->createExecution('completed');

        $this->assertFalse($this->stateMachine->canTransition($execution, 'running'));
        $this->assertFalse($this->stateMachine->canTransition($execution, 'paused'));
        $this->assertFalse($this->stateMachine->canTransition($execution, 'failed'));
    }

    /** @test */
    public function it_disallows_any_transition_from_failed(): void
    {
        $execution = $this->createExecution('failed');

        $this->assertFalse($this->stateMachine->canTransition($execution, 'running'));
        $this->assertFalse($this->stateMachine->canTransition($execution, 'paused'));
    }

    /** @test */
    public function it_disallows_any_transition_from_cancelled(): void
    {
        $execution = $this->createExecution('cancelled');

        $this->assertFalse($this->stateMachine->canTransition($execution, 'running'));
        $this->assertFalse($this->stateMachine->canTransition($execution, 'completed'));
    }

    /** @test */
    public function it_transitions_and_updates_status(): void
    {
        $execution = $this->createExecution('pending');

        $this->stateMachine->transition($execution, 'running');

        $this->assertEquals('running', $execution->fresh()->status);
    }

    /** @test */
    public function it_sets_finished_at_on_terminal_transition(): void
    {
        $execution = $this->createExecution('running');

        $this->stateMachine->transition($execution, 'completed');

        $this->assertNotNull($execution->fresh()->finished_at);
    }

    /** @test */
    public function it_throws_on_invalid_transition(): void
    {
        $execution = $this->createExecution('completed');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot transition from 'completed' to 'running'");

        $this->stateMachine->transition($execution, 'running');
    }

    /** @test */
    public function it_identifies_terminal_states(): void
    {
        $this->assertTrue($this->stateMachine->isTerminal('completed'));
        $this->assertTrue($this->stateMachine->isTerminal('failed'));
        $this->assertTrue($this->stateMachine->isTerminal('cancelled'));
        $this->assertFalse($this->stateMachine->isTerminal('running'));
        $this->assertFalse($this->stateMachine->isTerminal('pending'));
        $this->assertFalse($this->stateMachine->isTerminal('paused'));
    }

    /** @test */
    public function it_returns_allowed_transitions(): void
    {
        $this->assertEquals(['running', 'cancelled'], $this->stateMachine->getAllowedTransitions('pending'));
        $this->assertEquals(['paused', 'completed', 'failed', 'cancelled'], $this->stateMachine->getAllowedTransitions('running'));
        $this->assertEquals(['running', 'cancelled'], $this->stateMachine->getAllowedTransitions('paused'));
        $this->assertEquals([], $this->stateMachine->getAllowedTransitions('completed'));
    }

    /** @test */
    public function it_completes_execution(): void
    {
        $execution = $this->createExecution('running');

        $this->stateMachine->complete($execution);

        $this->assertTrue($execution->fresh()->isCompleted());
    }

    /** @test */
    public function it_fails_execution(): void
    {
        $execution = $this->createExecution('running');

        $this->stateMachine->fail($execution);

        $this->assertTrue($execution->fresh()->isFailed());
    }

    /** @test */
    public function it_pauses_execution(): void
    {
        $execution = $this->createExecution('running');

        $this->stateMachine->pause($execution);

        $this->assertTrue($execution->fresh()->isPaused());
    }

    /** @test */
    public function it_cancels_execution(): void
    {
        $execution = $this->createExecution('running');

        $this->stateMachine->cancel($execution);

        $this->assertTrue($execution->fresh()->isCancelled());
    }

    /** @test */
    public function it_starts_execution(): void
    {
        $execution = $this->createExecution('pending');

        $this->stateMachine->start($execution);

        $this->assertTrue($execution->fresh()->isRunning());
    }
}