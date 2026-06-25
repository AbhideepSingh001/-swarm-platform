<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskOrchestrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    private TaskOrchestrationService $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = app(TaskOrchestrationService::class);
        
        Event::fake([
            \App\Events\Tasks\TaskCreated::class,
            \App\Events\Tasks\TaskStatusChanged::class,
            \App\Events\Tasks\TaskAssigned::class,
            \App\Events\Tasks\TaskAssignmentAccepted::class,
            \App\Events\Tasks\TaskProgressUpdated::class,
            \App\Events\Tasks\TaskCommentAdded::class,
            \App\Events\Tasks\OrchestrationCompleted::class,
        ]);
    }

    private function createPlan(): Plan
    {
        return Plan::create([
            'title' => 'Test Plan',
            'goal' => 'Test Plan Goal',
            'status' => 'pending',
        ]);
    }

    public function test_can_create_task(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $task = $this->orchestrator->createTask([
            'title' => 'Test Task',
            'description' => 'A test task',
            'priority' => 'high',
            'task_type' => 'code_generation',
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Test Task',
            'status' => 'pending',
        ]);

        $this->assertNotNull($task->orchestration_id);
        $this->assertStringStartsWith('orch_', $task->orchestration_id);
    }

    public function test_can_create_task_with_subtasks(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $task = $this->orchestrator->createTask([
            'title' => 'Parent Task',
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ], [
            ['title' => 'Subtask 1', 'type' => 'testing'],
            ['title' => 'Subtask 2', 'type' => 'documentation'],
        ]);

        $this->assertCount(2, $task->subtasks);
        $this->assertEquals('pending', $task->subtasks->first()->status);
    }

    public function test_task_assignment_to_agent(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();
        
        $session = \App\Models\SwarmSession::create([
    'goal' => 'Test session',
    'status' => 'running',
]);

$agent = Agent::create([
    'session_id' => $session->id,
    'role' => 'coder',
    'name' => 'TestAgent',
    'status' => 'online',
]);
        $task = $this->orchestrator->createTask([
            'title' => 'Code Review Task',
            'task_type' => 'code_review',
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $assignment = $this->orchestrator->assignTask($task, $agent);

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'assignable_type' => Agent::class,
            'assignable_id' => $agent->id,
            'role' => 'primary',
        ]);

        $this->assertEquals('assigned', $task->fresh()->status);
    }

    public function test_task_status_transition(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $task = $this->orchestrator->createTask([
            'title' => 'Transition Test',
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $task->transitionTo('in_progress');

        $this->assertEquals('in_progress', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->started_at);
    }

    public function test_task_completion(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $task = $this->orchestrator->createTask([
            'title' => 'Complete Test',
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $task->transitionTo('in_progress');
        $this->orchestrator->completeTask($task, ['output' => 'done']);

        $this->assertEquals('completed', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertEquals(['output' => 'done'], $task->fresh()->result);
    }

    public function test_task_dependency_blocks_execution(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $parent = $this->orchestrator->createTask([
            'title' => 'Parent',
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $child = $this->orchestrator->createTask([
            'title' => 'Child',
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->orchestrator->addDependency($child, $parent, 'requires');

        $this->assertFalse($child->canStart());
        $this->assertTrue($parent->canStart());

        $parent->transitionTo('completed');

        $this->assertTrue($child->fresh()->canStart());
    }

        public function test_workflow_creation(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();

        // Create an online agent for auto-assignment
        $session = \App\Models\SwarmSession::create([
            'goal' => 'Test session',
            'status' => 'running',
        ]);

        Agent::create([
            'session_id' => $session->id,
            'role' => 'coder',
            'name' => 'TestAgent',
            'status' => 'online',
        ]);

        $workflow = $this->orchestrator->createWorkflow('Test Workflow', [
            ['title' => 'Step 1', 'type' => 'code_generation'],
            ['title' => 'Step 2', 'type' => 'testing', 'depends_on' => [0]],
            ['title' => 'Step 3', 'type' => 'documentation', 'depends_on' => [1]],
        ], $user->id, $plan->id);

        $this->assertCount(3, $workflow->subtasks);

        $step2 = $workflow->subtasks->skip(1)->first();
        $this->assertCount(1, $step2->blockedBy);
    }

    public function test_api_task_crud(): void
    {
        $this->markTestSkipped('TaskController not implemented yet');
    }

    public function test_api_task_stats(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $this->orchestrator->createTask([
            'title' => 'Stat Task 1',
            'status' => 'completed',
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->orchestrator->createTask([
            'title' => 'Stat Task 2',
            'status' => 'pending',
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/tasks/stats/overview');

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('by_status.completed', 1)
            ->assertJsonPath('by_status.pending', 1);
    }

    public function test_task_soft_delete(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $task = $this->orchestrator->createTask([
            'title' => 'Delete Me',
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $task->delete();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_task_overdue_detection(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $task = $this->orchestrator->createTask([
            'title' => 'Overdue Task',
            'deadline_at' => now()->subDay(),
            'creator_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertTrue($task->isOverdue());
    }
}