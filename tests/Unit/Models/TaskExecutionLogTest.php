<?php

namespace Tests\Unit\Models;

use App\Models\TaskExecutionLog;
use App\Models\TaskResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskExecutionLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_task_result(): void
    {
        $result = TaskResult::factory()->create();
        $log = TaskExecutionLog::factory()->create(['task_result_id' => $result->id]);

        $this->assertInstanceOf(TaskResult::class, $log->taskResult);
    }

    public function test_scope_for_level_filters_correctly(): void
    {
        $result = TaskResult::factory()->create();
        TaskExecutionLog::factory()->count(2)->create(['task_result_id' => $result->id, 'level' => 'error']);
        TaskExecutionLog::factory()->create(['task_result_id' => $result->id, 'level' => 'info']);

        $this->assertCount(2, TaskExecutionLog::forLevel('error')->get());
    }
}