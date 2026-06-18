<?php

namespace Tests\Feature;

use App\Models\SwarmSession;
use App\Services\SharedMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedMemoryTest extends TestCase
{
    use RefreshDatabase; // Automatically resets database after each test

    public function test_can_create_session_and_store_in_redis(): void
    {
        // Step 1: Create a session in the database
        $session = SwarmSession::create([
            'goal' => 'Build authentication system',
            'status' => 'running',
        ]);

        // Verify session exists in database
        $this->assertDatabaseHas('swarm_sessions', [
            'id' => $session->id,
            'goal' => 'Build authentication system',
        ]);

        // Step 2: Store something in Redis for this session
        $memory = new SharedMemoryService();
        $memory->store("session:{$session->id}:status", [
            'phase' => 'initialization',
            'active_agents' => 2,
        ]);

        // Step 3: Retrieve from Redis and verify
        $data = $memory->retrieve("session:{$session->id}:status");

        $this->assertNotNull($data);
        $this->assertEquals('initialization', $data['phase']);
        $this->assertEquals(2, $data['active_agents']);
    }

    public function test_can_store_and_retrieve_agent_memory(): void
    {
        $session = SwarmSession::create([
            'goal' => 'Test agent memory',
            'status' => 'running',
        ]);

        $memory = new SharedMemoryService();

        // Store planner agent memory
        $memory->storeAgentMemory($session->id, 'planner', [
            'current_task' => 'decompose_goal',
            'tasks_created' => 5,
        ]);

        // Retrieve it
        $agentMemory = $memory->getAgentMemory($session->id, 'planner');

        $this->assertNotNull($agentMemory);
        $this->assertEquals('decompose_goal', $agentMemory['current_task']);
        $this->assertEquals(5, $agentMemory['tasks_created']);
    }

    public function test_can_get_full_session_state(): void
    {
        $session = SwarmSession::create([
            'goal' => 'Test full state',
            'status' => 'running',
        ]);

        $memory = new SharedMemoryService();

        // Store multiple things
        $memory->storeAgentMemory($session->id, 'planner', ['status' => 'working']);
        $memory->storeAgentMemory($session->id, 'coder', ['status' => 'idle']);
        $memory->storeMessage($session->id, 'planner', 'coder', 'task_assignment', [
            'task_id' => 1,
            'title' => 'Create routes',
        ]);

        // Get full state
        $state = $memory->getSessionState($session->id);

        $this->assertEquals($session->id, $state['session_id']);
        $this->assertNotEmpty($state['agents']);
        $this->assertNotEmpty($state['messages']);
        $this->assertArrayHasKey('timestamp', $state);
    }
}