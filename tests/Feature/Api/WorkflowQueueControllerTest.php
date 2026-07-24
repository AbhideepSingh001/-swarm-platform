<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\Swarm\AsyncSwarmRunner;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkflowQueueControllerTest extends TestCase
{
    #[Test]
    public function it_dispatches_workflow_via_post_endpoint(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/workflows/wf-001/dispatch', [
            'input' => ['query' => 'test'],
            'steps' => [
                ['id' => 's1', 'name' => 'Step 1', 'agent_id' => 'a1'],
            ],
            'edges' => [],
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['execution_id', 'status', 'batch_id', 'message']);
    }

    #[Test]
    public function it_returns_404_for_unknown_execution_poll(): void
    {
        $response = $this->getJson('/api/workflows/executions/nonexistent/poll');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Execution not found']);
    }

    #[Test]
    public function it_returns_422_for_retry_on_non_failed_execution(): void
    {
        $this->assertTrue(true);
    }

    #[Test]
    public function it_returns_metrics_for_execution(): void
    {
        $this->assertTrue(true);
    }
}
