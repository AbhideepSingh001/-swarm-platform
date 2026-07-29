<?php

namespace Tests\Unit\Services\Swarm;

use App\Models\DeadLetter;
use App\Services\Swarm\DeadLetterQueue;
use App\Services\Swarm\FailureAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class DeadLetterQueueTest extends TestCase
{
    use RefreshDatabase;

    protected DeadLetterQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queue = new DeadLetterQueue();
    }

    /** @test */
    public function it_records_a_dead_letter_with_full_context(): void
    {
        $exception = new \RuntimeException('Something broke');

        $deadLetter = $this->queue->record([
            'execution_id' => 'exec-123',
            'step_id' => 'step-456',
            'agent_id' => 'agent-alpha',
            'error' => $exception,
            'step_config' => ['prompt' => 'test'],
            'context' => ['user_id' => 1],
            'retry_count' => 3,
        ]);

        $this->assertDatabaseHas('dead_letters', [
            'execution_id' => 'exec-123',
            'step_id' => 'step-456',
            'agent_id' => 'agent-alpha',
            'retry_count' => 3,
            'status' => 'open',
        ]);

        $this->assertEquals('Something broke', $deadLetter->error_message);
        $this->assertEquals(['prompt' => 'test'], $deadLetter->step_config);
    }

    /** @test */
    public function it_categorizes_failures_on_record(): void
    {
        $exception = new \Exception('Connection timed out');

        $deadLetter = $this->queue->record([
            'execution_id' => 'exec-123',
            'step_id' => 'step-1',
            'agent_id' => 'agent-1',
            'error' => $exception,
            'step_config' => [],
            'context' => [],
            'retry_count' => 0,
        ]);

        $this->assertEquals(FailureAnalyzer::TIMEOUT, $deadLetter->failure_category);
    }

    /** @test */
    public function it_retrieves_a_dead_letter_by_id(): void
    {
        $created = DeadLetter::factory()->create();

        $found = $this->queue->retrieve($created->id);

        $this->assertNotNull($found);
        $this->assertEquals($created->execution_id, $found->execution_id);
    }

    /** @test */
    public function it_prunes_old_resolved_dead_letters(): void
    {
        DeadLetter::factory()->create(['status' => 'resolved', 'updated_at' => now()->subDays(40)]);
        DeadLetter::factory()->create(['status' => 'resolved', 'updated_at' => now()->subDays(10)]);
        DeadLetter::factory()->create(['status' => 'open', 'updated_at' => now()->subDays(40)]);

        $deleted = $this->queue->pruneResolved(30);

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseCount('dead_letters', 2);
    }
}
