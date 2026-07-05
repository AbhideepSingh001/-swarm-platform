<?php

namespace Tests\Feature\Services\Swarm;

use App\Events\StepCompleted;
use App\Events\WorkflowFinished;
use App\Events\WorkflowStarted;
use App\Models\SwarmWorkflow;
use App\Models\WorkflowExecution;
use App\Services\Swarm\DAGResolver;
use App\Services\Swarm\SwarmRunner;
use App\Services\Swarm\WorkflowStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SwarmRunnerTest extends TestCase
{
    use RefreshDatabase;

    private SwarmRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new SwarmRunner(new DAGResolver(), new WorkflowStateMachine());
    }

    private function createWorkflow(array $steps): SwarmWorkflow
    {
        $workflow = SwarmWorkflow::factory()->create();

        foreach ($steps as $index => $step) {
            $workflow->steps()->create([
                'name' => $step['name'],
                'agent' => $step['agent'],
                'task' => $step['task'],
                'depends_on' => $step['depends_on'] ?? [],
                'order' => $index,
                'max_retries' => $step['max_retries'] ?? 0,
            ]);
        }

        return $workflow;
    }

    public function test_it_executes_simple_workflow(): void
    {
        Event::fake([WorkflowStarted::class, StepCompleted::class, WorkflowFinished::class]);

        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent1', 'task' => 'task1'],
            ['name' => 'step2', 'agent' => 'agent2', 'task' => 'task2', 'depends_on' => ['step1']],
        ]);

        $execution = $this->runner->execute($workflow);

        $this->assertTrue($execution->isCompleted());
        $this->assertCount(2, $execution->results);
        $this->assertArrayHasKey('step1', $execution->results);
        $this->assertArrayHasKey('step2', $execution->results);
        Event::assertDispatched(WorkflowStarted::class);
        Event::assertDispatched(WorkflowFinished::class);
    }

    public function test_it_executes_workflow_with_parallel_steps(): void
    {
        Event::fake([WorkflowStarted::class, StepCompleted::class, WorkflowFinished::class]);

        $workflow = $this->createWorkflow([
            ['name' => 'research', 'agent' => 'researcher', 'task' => 'gather'],
            ['name' => 'outline', 'agent' => 'planner', 'task' => 'plan'],
            ['name' => 'draft', 'agent' => 'writer', 'task' => 'write', 'depends_on' => ['research', 'outline']],
        ]);

        $execution = $this->runner->execute($workflow);

        $this->assertTrue($execution->isCompleted());
        $this->assertCount(3, $execution->results);
        Event::assertDispatched(WorkflowStarted::class);
        Event::assertDispatchedTimes(StepCompleted::class, 3);
        Event::assertDispatched(WorkflowFinished::class);
    }

    public function test_it_dispatches_events_for_each_step(): void
    {
        Event::fake([StepCompleted::class]);

        $workflow = $this->createWorkflow([
            ['name' => 'a', 'agent' => 'agent', 'task' => 'task'],
            ['name' => 'b', 'agent' => 'agent', 'task' => 'task', 'depends_on' => ['a']],
        ]);

        $this->runner->execute($workflow);

        Event::assertDispatchedTimes(StepCompleted::class, 2);
    }

    public function test_it_stores_step_results(): void
    {
        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent1', 'task' => 'task1'],
        ]);

        $execution = $this->runner->execute($workflow);

        $result = $execution->getStepResult('step1');
        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('agent1', $result['output']['agent']);
        $this->assertEquals('task1', $result['output']['task']);
    }

    public function test_it_tracks_execution_progress(): void
    {
        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent', 'task' => 'task'],
            ['name' => 'step2', 'agent' => 'agent', 'task' => 'task', 'depends_on' => ['step1']],
            ['name' => 'step3', 'agent' => 'agent', 'task' => 'task', 'depends_on' => ['step2']],
        ]);

        $execution = $this->runner->execute($workflow);
        $status = $this->runner->getStatus($execution);

        $this->assertEquals(100.0, $status['progress_percent']);
        $this->assertEquals(3, $status['total_steps']);
        $this->assertCount(3, $status['completed_steps']);
    }

    public function test_it_passes_context_to_steps(): void
    {
        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent', 'task' => 'task'],
        ]);

        $context = ['topic' => 'AI', 'depth' => 'deep'];
        $execution = $this->runner->execute($workflow, $context);

        $result = $execution->getStepResult('step1');
        $this->assertEquals($context, $result['output']['context']);
    }

    public function test_it_pauses_execution(): void
    {
        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent', 'task' => 'task'],
        ]);

        $execution = WorkflowExecution::factory()->create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'running',
        ]);

        $this->runner->pause($execution);

        $this->assertTrue($execution->fresh()->isPaused());
    }

    public function test_it_cancels_execution(): void
    {
        Event::fake([WorkflowFinished::class]);

        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent', 'task' => 'task'],
        ]);

        $execution = WorkflowExecution::factory()->create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'running',
        ]);

        $this->runner->cancel($execution);

        $this->assertTrue($execution->fresh()->isCancelled());
        Event::assertDispatched(WorkflowFinished::class, function ($event) {
            return $event->finalStatus === 'cancelled';
        });
    }

    public function test_it_resumes_paused_execution(): void
    {
        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent', 'task' => 'task'],
            ['name' => 'step2', 'agent' => 'agent', 'task' => 'task', 'depends_on' => ['step1']],
        ]);

        $pausedExecution = WorkflowExecution::factory()->create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'paused',
            'results' => ['step1' => ['success' => true]],
        ]);

        $resumed = $this->runner->resume($pausedExecution);

        $this->assertTrue($resumed->isCompleted());
    }

    public function test_it_throws_when_resuming_non_paused_execution(): void
    {
        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent', 'task' => 'task'],
        ]);

        $execution = $this->runner->execute($workflow);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Execution must be paused to resume');

        $this->runner->resume($execution);
    }

    public function test_it_creates_execution_record(): void
    {
        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent', 'task' => 'task'],
        ]);

        $execution = $this->runner->execute($workflow, ['key' => 'value']);

        $this->assertDatabaseHas('workflow_executions', [
            'id' => $execution->id,
            'swarm_workflow_id' => $workflow->id,
            'status' => 'completed',
        ]);
    }

    public function test_it_sets_started_at_on_execution(): void
    {
        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent', 'task' => 'task'],
        ]);

        $execution = $this->runner->execute($workflow);

        $this->assertNotNull($execution->started_at);
    }

    public function test_it_sets_finished_at_on_completion(): void
    {
        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent', 'task' => 'task'],
        ]);

        $execution = $this->runner->execute($workflow);

        $this->assertNotNull($execution->finished_at);
    }

    public function test_it_calculates_progress_for_partial_execution(): void
    {
        $workflow = $this->createWorkflow([
            ['name' => 'step1', 'agent' => 'agent', 'task' => 'task'],
            ['name' => 'step2', 'agent' => 'agent', 'task' => 'task'],
        ]);

        $execution = WorkflowExecution::factory()->create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'running',
            'results' => ['step1' => ['success' => true]],
        ]);

        $status = $this->runner->getStatus($execution);

        $this->assertEquals(50.0, $status['progress_percent']);
    }
}