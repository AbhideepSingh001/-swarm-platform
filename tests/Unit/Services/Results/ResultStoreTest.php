<?php

namespace Tests\Unit\Services\Results;

use App\Models\Task;
use App\Models\TaskResult;
use App\Services\Results\ResultStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultStoreTest extends TestCase
{
    use RefreshDatabase;

    private ResultStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = app(ResultStore::class);
    }

    public function test_create_persists_task_result(): void
    {
        $task = Task::factory()->create();

        $result = $this->store->create($task->id);

        $this->assertInstanceOf(TaskResult::class, $result);
        $this->assertDatabaseHas('task_results', [
            'id' => $result->id,
            'task_id' => $task->id,
            'status' => 'pending',
        ]);
    }

    public function test_update_modifies_result(): void
    {
        $result = TaskResult::factory()->create(['status' => 'pending']);

        $updated = $this->store->update($result->id, ['status' => 'completed']);

        $this->assertEquals('completed', $updated->status);
        $this->assertDatabaseHas('task_results', ['id' => $result->id, 'status' => 'completed']);
    }

    public function test_find_returns_result_with_relations(): void
    {
        $result = TaskResult::factory()->create();

        $found = $this->store->find($result->id);

        $this->assertInstanceOf(TaskResult::class, $found);
        $this->assertEquals($result->id, $found->id);
        $this->assertTrue($found->relationLoaded('executionLogs'));
        $this->assertTrue($found->relationLoaded('artifacts'));
    }

    public function test_find_returns_null_for_missing_id(): void
    {
        $this->assertNull($this->store->find(99999));
    }

    public function test_find_by_task_returns_latest_result(): void
    {
        $task = Task::factory()->create();
        TaskResult::factory()->create(['task_id' => $task->id, 'created_at' => now()->subDay()]);
        $latest = TaskResult::factory()->create(['task_id' => $task->id]);

        $found = $this->store->findByTask($task->id);

        $this->assertIsArray($found);
        $this->assertNotEmpty($found);
        $this->assertEquals($latest->id, $found[0]->id);
    }

    public function test_query_filters_by_status(): void
    {
        TaskResult::factory()->count(3)->create(['status' => 'completed']);
        TaskResult::factory()->create(['status' => 'failed']);

        $results = $this->store->query(['status' => 'completed'])->get();

        $this->assertCount(3, $results);
    }

    public function test_query_filters_by_task_id(): void
    {
        $task = Task::factory()->create();
        TaskResult::factory()->count(2)->create(['task_id' => $task->id]);
        TaskResult::factory()->create();

        $results = $this->store->query(['task_id' => $task->id])->get();

        $this->assertCount(2, $results);
    }

    public function test_query_filters_by_date_range(): void
    {
        TaskResult::factory()->create(['created_at' => now()->subDays(10)]);
        TaskResult::factory()->create(['created_at' => now()->subDays(5)]);
        TaskResult::factory()->create(['created_at' => now()]);

        $results = $this->store->query([
            'from' => now()->subDays(7)->toDateTimeString(),
            'to' => now()->toDateTimeString(),
        ])->get();

        $this->assertCount(2, $results);
    }

    public function test_query_searches_error_message(): void
    {
        TaskResult::factory()->create(['error_message' => 'Database connection failed']);
        TaskResult::factory()->create(['error_message' => null]);

        $results = $this->store->query(['search' => 'Database'])->get();

        $this->assertCount(1, $results);
    }
}