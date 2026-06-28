<?php
// tests/Feature/Services/Results/DatabaseResultDriverTest.php

namespace Tests\Feature\Services\Results;

use App\Models\Artifact;
use App\Models\Task;
use App\Models\TaskExecutionLog;
use App\Models\TaskResult;
use App\Services\Results\Drivers\DatabaseResultDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseResultDriverTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseResultDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new DatabaseResultDriver();
    }

    public function test_create_task_result(): void
    {
        $task = Task::factory()->create();

        $result = $this->driver->create($task->id);

        $this->assertDatabaseHas('task_results', [
            'id' => $result->id,
            'task_id' => $task->id,
            'status' => 'pending',
        ]);
    }

    public function test_find_task_result_with_relations(): void
    {
        $result = TaskResult::factory()->create();
        TaskExecutionLog::factory()->create(['task_result_id' => $result->id]);
        Artifact::factory()->create(['task_result_id' => $result->id]);

        $found = $this->driver->find($result->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->relationLoaded('executionLogs'));
        $this->assertTrue($found->relationLoaded('artifacts'));
    }

    public function test_find_by_task_with_filters(): void
    {
        $task = Task::factory()->create();
        TaskResult::factory()->completed()->create(['task_id' => $task->id]);
        TaskResult::factory()->failed()->create(['task_id' => $task->id]);

        $results = $this->driver->findByTask($task->id, ['status' => 'completed']);

        $this->assertCount(1, $results);
        $this->assertEquals('completed', $results[0]->status);
    }

    public function test_update_task_result(): void
    {
        $result = TaskResult::factory()->create(['status' => 'pending']);

        $updated = $this->driver->update($result->id, ['status' => 'running']);

        $this->assertEquals('running', $updated->status);
        $this->assertDatabaseHas('task_results', ['id' => $result->id, 'status' => 'running']);
    }

    public function test_delete_task_result_cascades(): void
    {
        $result = TaskResult::factory()->create();
        TaskExecutionLog::factory()->create(['task_result_id' => $result->id]);
        Artifact::factory()->create(['task_result_id' => $result->id]);

        $deleted = $this->driver->delete($result->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('task_results', ['id' => $result->id]);
        $this->assertDatabaseMissing('task_execution_logs', ['task_result_id' => $result->id]);
        $this->assertDatabaseMissing('task_artifacts', ['task_result_id' => $result->id]);
    }

    public function test_log_creates_execution_log(): void
    {
        $result = TaskResult::factory()->create();

        $this->driver->log($result->id, 'info', 'Task started', 'initialization', ['step' => 1]);

        $this->assertDatabaseHas('task_execution_logs', [
            'task_result_id' => $result->id,
            'level' => 'info',
            'message' => 'Task started',
        ]);
    }

    public function test_attach_artifact(): void
    {
        $result = TaskResult::factory()->create();

        $this->driver->attachArtifact($result->id, 'output.json', 'json', 'local', 'path/to/file.json', 1024);

        $this->assertDatabaseHas('task_artifacts', [
            'task_result_id' => $result->id,
            'name' => 'output.json',
            'type' => 'json',
        ]);
    }
}