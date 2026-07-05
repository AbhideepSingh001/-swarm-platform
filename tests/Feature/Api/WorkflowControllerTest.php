<?php

namespace Tests\Feature\Api;

use App\Models\SwarmWorkflow;
use App\Models\WorkflowExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_lists_workflows(): void
    {
        SwarmWorkflow::factory()->count(3)->create();

        $response = $this->getJson('/api/workflows');

        $response->assertOk()
            ->assertJsonCount(3);
    }

    /** @test */
    public function it_creates_workflow_with_steps(): void
    {
        $response = $this->postJson('/api/workflows', [
            'name' => 'content-pipeline',
            'description' => 'Content creation workflow',
            'steps' => [
                ['name' => 'research', 'agent' => 'researcher', 'task' => 'gather-sources'],
                ['name' => 'draft', 'agent' => 'writer', 'task' => 'write-article', 'depends_on' => ['research']],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'content-pipeline')
            ->assertJsonCount(2, 'steps');

        $this->assertDatabaseHas('swarm_workflows', ['name' => 'content-pipeline']);
    }

    /** @test */
    public function it_validates_workflow_creation(): void
    {
        $response = $this->postJson('/api/workflows', [
            'name' => '',
            'steps' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'steps']);
    }

    /** @test */
    public function it_shows_workflow_definition(): void
    {
        $workflow = SwarmWorkflow::factory()->create();
        $workflow->steps()->create(['name' => 'step1', 'agent' => 'agent', 'task' => 'task', 'order' => 0]);

        $response = $this->getJson("/api/workflows/{$workflow->id}");

        $response->assertOk()
            ->assertJsonPath('id', $workflow->id)
            ->assertJsonCount(1, 'steps');
    }

    /** @test */
        /** @test */
    public function it_executes_workflow(): void
    {
        $workflow = SwarmWorkflow::factory()->create(['is_active' => true]);
        $workflow->steps()->create(['name' => 'step1', 'agent' => 'agent', 'task' => 'task', 'order' => 0]);

        $response = $this->postJson("/api/workflows/{$workflow->id}/execute", [
            'context' => ['topic' => 'AI'],
        ]);

        $response->assertAccepted()
            ->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('workflow_executions', [
            'swarm_workflow_id' => $workflow->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function it_rejects_execution_of_inactive_workflow(): void
    {
        $workflow = SwarmWorkflow::factory()->create(['is_active' => false]);

        $response = $this->postJson("/api/workflows/{$workflow->id}/execute");

        $response->assertForbidden()
            ->assertJsonPath('error', 'Workflow is inactive');
    }

    /** @test */
    public function it_gets_execution_status(): void
    {
        $workflow = SwarmWorkflow::factory()->create();
        $execution = WorkflowExecution::factory()->create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'running',
        ]);

        $response = $this->getJson("/api/workflows/executions/{$execution->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'running')
            ->assertJsonPath('workflow', $workflow->name);
    }

    /** @test */
    public function it_pauses_running_execution(): void
    {
        $execution = WorkflowExecution::factory()->create(['status' => 'running']);

        $response = $this->postJson("/api/workflows/executions/{$execution->id}/pause");

        $response->assertOk()
            ->assertJsonPath('status', 'paused');
    }

    /** @test */
    public function it_rejects_pause_on_non_running_execution(): void
    {
        $execution = WorkflowExecution::factory()->create(['status' => 'completed']);

        $response = $this->postJson("/api/workflows/executions/{$execution->id}/pause");

        $response->assertStatus(409)
            ->assertJsonPath('error', 'Execution is not running');
    }

    /** @test */
    public function it_resumes_paused_execution(): void
    {
        $workflow = SwarmWorkflow::factory()->create();
        $execution = WorkflowExecution::factory()->create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'paused',
        ]);

        $response = $this->postJson("/api/workflows/executions/{$execution->id}/resume");

        $response->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    /** @test */
    public function it_rejects_resume_on_non_paused_execution(): void
    {
        $execution = WorkflowExecution::factory()->create(['status' => 'running']);

        $response = $this->postJson("/api/workflows/executions/{$execution->id}/resume");

        $response->assertStatus(409)
            ->assertJsonPath('error', 'Execution is not paused');
    }

    /** @test */
    public function it_cancels_execution(): void
    {
        $execution = WorkflowExecution::factory()->create(['status' => 'running']);

        $response = $this->postJson("/api/workflows/executions/{$execution->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    /** @test */
    public function it_rejects_cancel_on_terminal_execution(): void
    {
        $execution = WorkflowExecution::factory()->create(['status' => 'completed']);

        $response = $this->postJson("/api/workflows/executions/{$execution->id}/cancel");

        $response->assertStatus(409)
            ->assertJsonPath('error', 'Execution is already terminal');
    }

    /** @test */
    public function it_gets_execution_results(): void
    {
        $workflow = SwarmWorkflow::factory()->create();
        $execution = WorkflowExecution::factory()->create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'completed',
            'results' => ['step1' => ['success' => true, 'output' => 'result']],
        ]);

        $response = $this->getJson("/api/workflows/executions/{$execution->id}/results");

        $response->assertOk()
            ->assertJsonPath('results.step1.success', true)
            ->assertJsonPath('completed_steps', ['step1']);
    }
}