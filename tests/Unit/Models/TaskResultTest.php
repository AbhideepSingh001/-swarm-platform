<?php
// tests/Unit/Models/TaskResultTest.php

namespace Tests\Unit\Models;

use App\Models\Agent;
use App\Models\Artifact;
use App\Models\Task;
use App\Models\TaskExecutionLog;
use App\Models\TaskResult;
use App\Models\WorkflowExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_result_belongs_to_task(): void
    {
        $task = Task::factory()->create();
        $result = TaskResult::factory()->create(['task_id' => $task->id]);

        $this->assertInstanceOf(Task::class, $result->task);
        $this->assertEquals($task->id, $result->task->id);
    }

    public function test_task_result_belongs_to_agent(): void
    {
        $agent = Agent::factory()->create();
        $result = TaskResult::factory()->create(['agent_id' => $agent->id]);

        $this->assertInstanceOf(Agent::class, $result->agent);
        $this->assertEquals($agent->id, $result->agent->id);
    }

    public function test_task_result_has_many_execution_logs(): void
    {
        $result = TaskResult::factory()->create();
        TaskExecutionLog::factory()->count(3)->create(['task_result_id' => $result->id]);

        $this->assertCount(3, $result->executionLogs);
        $this->assertContainsOnlyInstancesOf(TaskExecutionLog::class, $result->executionLogs);
    }

    public function test_task_result_has_many_artifacts(): void
    {
        $result = TaskResult::factory()->create();
        Artifact::factory()->count(2)->create(['task_result_id' => $result->id]);

        $this->assertCount(2, $result->artifacts);
        $this->assertContainsOnlyInstancesOf(Artifact::class, $result->artifacts);
    }

    public function test_mark_as_running_sets_status_and_started_at(): void
    {
        $result = TaskResult::factory()->create(['status' => 'pending']);

        $result->markAsRunning();

        $this->assertEquals('running', $result->fresh()->status);
        $this->assertNotNull($result->fresh()->started_at);
    }

    public function test_mark_as_completed_sets_output_and_duration(): void
    {
        $result = TaskResult::factory()->create([
            'status' => 'running',
            'started_at' => now()->subSeconds(2),
        ]);

        $result->markAsCompleted(['data' => 'test'], ['model' => 'gpt-4']);

        $fresh = $result->fresh();
        $this->assertEquals('completed', $fresh->status);
        $this->assertEquals(['data' => 'test'], $fresh->output);
        $this->assertNotNull($fresh->duration_ms);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_mark_as_failed_sets_error_and_duration(): void
    {
        $result = TaskResult::factory()->create([
            'status' => 'running',
            'started_at' => now()->subSeconds(1),
        ]);

        $result->markAsFailed('Connection timeout');

        $fresh = $result->fresh();
        $this->assertEquals('failed', $fresh->status);
        $this->assertEquals('Connection timeout', $fresh->error_message);
        $this->assertNotNull($fresh->duration_ms);
    }
}