<?php

namespace Tests\Feature\Swarm;

use App\Models\SwarmWorkflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use App\Services\Swarm\AgentDispatcher;
use App\Services\Swarm\AsyncSwarmRunner;
use App\Services\Swarm\DAGResolver;
use App\Services\Swarm\StepRetryDecorator;
use App\Services\Swarm\WorkflowBatchMonitor;
use App\Services\Swarm\WorkflowStateMachine;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Day18AsyncQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_agent_dispatcher_can_dispatch_workflow(): void
    {
        $workflow = SwarmWorkflow::create([
            'name' => 'Test Workflow',
            'config' => [],
            'is_active' => true,
        ]);

        $workflow->steps()->create([
            'name' => 'step1',
            'agent' => 'test-agent',
            'task' => 'test-task',
            'depends_on' => [],
            'order' => 0,
        ]);

        $execution = WorkflowExecution::create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'pending',
            'context' => ['input' => 'test'],
        ]);

        $dispatcher = app(AgentDispatcher::class);
        $batchId = $dispatcher->dispatch($execution);

        $this->assertNotEmpty($batchId);
        $execution->refresh();
        $this->assertEquals('running', $execution->status);
        $this->assertArrayHasKey('batch_id', $execution->checkpoint ?? []);
    }

    public function test_step_retry_decorator_retries_on_failure(): void
    {
        $decorator = app(StepRetryDecorator::class);
        $execution = WorkflowExecution::factory()->make();
        $step = WorkflowStep::factory()->make(['max_retries' => 2]);

        $attempts = 0;
        $result = $decorator->execute($execution, $step, function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new \RuntimeException('Temporary failure');
            }
            return 'success';
        });

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempts);
    }

    public function test_step_retry_decorator_throws_after_max_retries(): void
    {
        $decorator = app(StepRetryDecorator::class);
        $execution = WorkflowExecution::factory()->make();
        $step = WorkflowStep::factory()->make(['max_retries' => 1]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Permanent failure');

        $decorator->execute($execution, $step, function () {
            throw new \RuntimeException('Permanent failure');
        });
    }

    public function test_async_swarm_runner_can_cancel_workflow(): void
    {
        $runner = app(AsyncSwarmRunner::class);
        $workflow = SwarmWorkflow::create([
            'name' => 'Cancel Test',
            'config' => [],
            'is_active' => true,
        ]);

        $execution = WorkflowExecution::create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'running',
            'checkpoint' => ['batch_id' => 'test-batch-123'],
        ]);

        $cancelled = $runner->cancel($execution);
        $this->assertTrue($cancelled);
        $this->assertEquals('cancelled', $execution->fresh()->status);
    }

    public function test_workflow_batch_monitor_tracks_executions(): void
    {
        $monitor = app(WorkflowBatchMonitor::class);
        $workflow = SwarmWorkflow::create([
            'name' => 'Monitor Test',
            'config' => [],
            'is_active' => true,
        ]);

        $execution = WorkflowExecution::create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'running',
        ]);

        $monitor->track($execution, 'batch-123');
        $tracking = $monitor->getTracking($execution);

        $this->assertNotNull($tracking);
        $this->assertEquals('batch-123', $tracking['batch_id']);
        $this->assertEquals($workflow->id, $tracking['workflow_id']);

        $monitor->untrack($execution);
        $this->assertNull($monitor->getTracking($execution));
    }

    public function test_workflow_queue_controller_returns_status(): void
    {
        $workflow = SwarmWorkflow::create([
            'name' => 'API Test',
            'config' => [],
            'is_active' => true,
        ]);

        $execution = WorkflowExecution::create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'running',
            'checkpoint' => ['batch_id' => 'test-batch'],
            'results' => ['step1' => 'done'],
        ]);

        $response = $this->actingAs($this->createUser())
            ->getJson("/workflow-executions/{$execution->id}/status");

        $response->assertOk()
            ->assertJsonPath('execution_id', $execution->id)
            ->assertJsonPath('status', 'running')
            ->assertJsonPath('results.step1', 'done');
    }

    public function test_workflow_queue_controller_can_cancel(): void
    {
        $workflow = SwarmWorkflow::create([
            'name' => 'Cancel API Test',
            'config' => [],
            'is_active' => true,
        ]);

        $execution = WorkflowExecution::create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'running',
            'checkpoint' => ['batch_id' => 'test-batch'],
        ]);

        $response = $this->actingAs($this->createUser())
            ->postJson("/workflow-executions/{$execution->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    public function test_workflow_queue_controller_rejects_cancel_for_terminal(): void
    {
        $workflow = SwarmWorkflow::create([
            'name' => 'Terminal Test',
            'config' => [],
            'is_active' => true,
        ]);

        $execution = WorkflowExecution::create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->createUser())
            ->postJson("/workflow-executions/{$execution->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Workflow is already terminal');
    }

    private function createUser(): \App\Models\User
    {
        return \App\Models\User::factory()->create();
    }
}
