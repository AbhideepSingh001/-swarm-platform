<?php

namespace Tests\Feature;

use App\Models\SwarmSession;
use App\Models\Agent;
use App\Services\SharedMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_a_writes_agent_b_reads(): void
    {
        // Step 1: Create session and two agents
        $session = SwarmSession::create([
            'goal' => 'Test inter-agent communication',
            'status' => 'running',
        ]);

        $planner = Agent::create([
            'session_id' => $session->id,
            'role' => 'planner',
            'name' => 'Planner-1',
            'status' => 'working',
        ]);

        $coder = Agent::create([
            'session_id' => $session->id,
            'role' => 'coder',
            'name' => 'Coder-1',
            'status' => 'idle',
        ]);

        // Step 2: Planner writes tasks to shared memory
        $memory = new SharedMemoryService();
        $memory->storeAgentMemory($session->id, 'planner', [
            'tasks' => [
                ['id' => 1, 'title' => 'Create migration', 'type' => 'database'],
                ['id' => 2, 'title' => 'Write model', 'type' => 'code'],
            ],
            'status' => 'tasks_decomposed',
        ]);

        // Step 3: Coder reads planner's memory
        $plannerMemory = $memory->getAgentMemory($session->id, 'planner');

        // Step 4: Verify Coder can see the tasks
        $this->assertNotNull($plannerMemory);
        $this->assertCount(2, $plannerMemory['tasks']);
        $this->assertEquals('Create migration', $plannerMemory['tasks'][0]['title']);

        // Step 5: Coder writes code artifact
        $memory->storeArtifact($session->id, '1', 'code', '<?php class User extends Model {}');

        // Step 6: Verify artifact exists
        $artifact = $memory->retrieve("session:{$session->id}:artifact:task:1");
        $this->assertNotNull($artifact);
        $this->assertEquals('code', $artifact['type']);
        $this->assertStringContainsString('class User', $artifact['content']);
    }

public function test_message_between_agents_is_stored(): void    {
        $session = SwarmSession::create([
            'goal' => 'Test messaging',
            'status' => 'running',
        ]);

        $memory = new SharedMemoryService();

        // Planner sends message to Coder
        $memory->storeMessage($session->id, 'planner', 'coder', 'task_assignment', [
            'task_id' => 5,
            'priority' => 'high',
            'description' => 'Build login controller',
        ]);

        // Verify message exists in session state
        $state = $memory->getSessionState($session->id);
        $this->assertNotEmpty($state['messages']);

        // Get first message and check content
        $firstMessage = array_values($state['messages'])[0];
        $this->assertEquals('planner', $firstMessage['from']);
        $this->assertEquals('coder', $firstMessage['to']);
        $this->assertEquals('task_assignment', $firstMessage['type']);
    }
}