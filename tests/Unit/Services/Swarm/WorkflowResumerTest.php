<?php

namespace Tests\Unit\Services\Swarm;

use App\Models\SwarmWorkflow;
use App\Models\WorkflowExecution;
use App\Services\Swarm\WorkflowResumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WorkflowResumerTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowResumer $resumer;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->resumer = new WorkflowResumer();
    }

    /** @test */
    public function it_resumes_workflow_from_checkpoint(): void
    {
        $workflow = SwarmWorkflow::factory()->create([
            'definition' => [
                'steps' => [
                    ['id' => 'step-1', 'agent_id' => 'a1'],
                    ['id' => 'step-2', 'agent_id' => 'a2'],
                    ['id' => 'step-3', 'agent_id' => 'a3'],
                ],
            ],
        ]);

        $execution = WorkflowExecution::factory()->create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'failed',
        ]);

        $result = $this->resumer->resumeFromCheckpoint((string) $execution->id, 'step-1');

        $this->assertTrue($result);
        Bus::assertBatched(function ($batch) {
            return count($batch->jobs) === 2;
        });
    }

    /** @test */
    public function it_skips_step_and_resumes_remaining(): void
    {
        $workflow = SwarmWorkflow::factory()->create([
            'definition' => [
                'steps' => [
                    ['id' => 'step-a', 'agent_id' => 'a1'],
                    ['id' => 'step-b', 'agent_id' => 'a2'],
                ],
            ],
        ]);

        $execution = WorkflowExecution::factory()->create([
            'swarm_workflow_id' => $workflow->id,
            'status' => 'failed',
        ]);

        $result = $this->resumer->resumeWithSkippedStep((string) $execution->id, 'step-a');

        $this->assertTrue($result);
        Bus::assertBatched(function ($batch) {
            return count($batch->jobs) === 1;
        });
    }

    /** @test */
    public function it_returns_false_for_missing_execution(): void
    {
        $result = $this->resumer->resumeFromCheckpoint('999999', 'step-1');
        $this->assertFalse($result);
    }
}
