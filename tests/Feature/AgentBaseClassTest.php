<?php

namespace Tests\Feature;

use App\Agents\Agent;
use App\Models\Agent as AgentModel;
use App\Models\SwarmSession;
use App\Services\SharedMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentBaseClassTest extends TestCase
{
    use RefreshDatabase;

    private SwarmSession $session;
    private AgentModel $agentModel;
    private SharedMemoryService $sharedMemory;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data before each test
        $this->session = SwarmSession::create([
            'goal' => 'Test agent foundation',
            'status' => 'running',
        ]);

        $this->agentModel = AgentModel::create([
            'session_id' => $this->session->id,
            'role' => 'tester',
            'name' => 'TestAgent-1',
            'status' => 'idle',
        ]);

        $this->sharedMemory = new SharedMemoryService();
    }

    public function test_abstract_class_cannot_be_instantiated_directly(): void
    {
        // This test proves Agent is abstract — you can't do "new Agent()"
        $this->expectException(\Error::class);
        
        new Agent($this->agentModel, $this->sharedMemory);
    }

    public function test_concrete_agent_can_be_created(): void
    {
        // Create a minimal concrete agent inline for testing
        $concreteAgent = new class($this->agentModel, $this->sharedMemory) extends Agent {
            protected string $role = 'tester';

            public function think(array $input): array
            {
                return ['thought' => 'thinking about ' . ($input['topic'] ?? 'nothing')];
            }

            public function act(array $plan): array
            {
                return ['result' => 'done'];
            }

            public function communicate(string $to, string $messageType, array $payload): void
            {
                // Test communication
            }
        };

        // Verify agent properties
        $this->assertEquals('tester', $concreteAgent->getRole());
        $this->assertEquals('TestAgent-1', $concreteAgent->getName());
        $this->assertEquals('idle', $concreteAgent->getStatus());
    }

    public function test_agent_can_think(): void
    {
        $concreteAgent = new class($this->agentModel, $this->sharedMemory) extends Agent {
            protected string $role = 'tester';

            public function think(array $input): array
            {
                return ['strategy' => 'analyze', 'output' => $input['goal']];
            }

            public function act(array $plan): array
            {
                return [];
            }

            public function communicate(string $to, string $messageType, array $payload): void
            {
            }
        };

        $result = $concreteAgent->think(['goal' => 'Build API']);

        $this->assertIsArray($result);
        $this->assertEquals('analyze', $result['strategy']);
        $this->assertEquals('Build API', $result['output']);
    }

    public function test_agent_can_act(): void
    {
        $concreteAgent = new class($this->agentModel, $this->sharedMemory) extends Agent {
            protected string $role = 'tester';

            public function think(array $input): array
            {
                return [];
            }

            public function act(array $plan): array
            {
                return ['tasks_created' => 3, 'status' => 'success'];
            }

            public function communicate(string $to, string $messageType, array $payload): void
            {
            }
        };

        $result = $concreteAgent->act(['strategy' => 'decompose']);

        $this->assertEquals(3, $result['tasks_created']);
        $this->assertEquals('success', $result['status']);
    }

    public function test_agent_can_remember_and_recall(): void
    {
        $concreteAgent = new class($this->agentModel, $this->sharedMemory) extends Agent {
            protected string $role = 'tester';

            public function think(array $input): array
            {
                return [];
            }

            public function act(array $plan): array
            {
                return [];
            }

            public function communicate(string $to, string $messageType, array $payload): void
            {
            }
        };

        // Store something in memory
        $concreteAgent->remember('last_action', 'planning');
        $concreteAgent->remember('task_count', 5);

        // Recall from memory
        $this->assertEquals('planning', $concreteAgent->recall('last_action'));
        $this->assertEquals(5, $concreteAgent->recall('task_count'));
        $this->assertNull($concreteAgent->recall('nonexistent_key'));

        // Verify it's stored in Redis too
        $stored = $this->sharedMemory->getAgentMemory($this->session->id, 'tester');
        $this->assertNotNull($stored);
        $this->assertEquals('planning', $stored['last_action']);
        $this->assertEquals(5, $stored['task_count']);
    }

    public function test_agent_can_update_status(): void
    {
        $concreteAgent = new class($this->agentModel, $this->sharedMemory) extends Agent {
            protected string $role = 'tester';

            public function think(array $input): array
            {
                return [];
            }

            public function act(array $plan): array
            {
                return [];
            }

            public function communicate(string $to, string $messageType, array $payload): void
            {
            }
        };

        // Status starts as idle
        $this->assertEquals('idle', $concreteAgent->getStatus());

        // Update to working
        $concreteAgent->setStatus('working');
        $this->assertEquals('working', $concreteAgent->getStatus());

        // Verify database updated
        $this->assertDatabaseHas('agents', [
            'id' => $this->agentModel->id,
            'status' => 'working',
        ]);
    }

    public function test_agent_can_communicate(): void
{
    $concreteAgent = new class($this->agentModel, $this->sharedMemory) extends Agent {
        protected string $role = 'tester';

        public function think(array $input): array
        {
            return [];
        }

        public function act(array $plan): array
        {
            return [];
        }

        public function communicate(string $to, string $messageType, array $payload): void
        {
            $this->sharedMemory->storeMessage(
                $this->sessionId,
                $this->role,
                $to,
                $messageType,
                $payload
            );
        }
    };

    // Send a message
    $concreteAgent->communicate('coder', 'task_assignment', [
        'task_id' => 1,
        'title' => 'Build controller',
    ]);

    // Verify message exists in session state
    $state = $this->sharedMemory->getSessionState($this->session->id);
    $this->assertNotEmpty($state['messages']);

    // Find the message from 'tester' (not just the first message)
    $testerMessage = null;
    foreach ($state['messages'] as $msg) {
        if ($msg['from'] === 'tester') {
            $testerMessage = $msg;
            break;
        }
    }

    $this->assertNotNull($testerMessage, 'Message from tester agent not found');
    $this->assertEquals('tester', $testerMessage['from']);
    $this->assertEquals('coder', $testerMessage['to']);
    $this->assertEquals('task_assignment', $testerMessage['type']);
    $this->assertEquals('Build controller', $testerMessage['payload']['title']);
}

    public function test_agent_can_read_messages(): void
    {
        // First, store a message from another agent
        $this->sharedMemory->storeMessage(
            $this->session->id,
            'planner',
            'tester',
            'status_update',
            ['status' => 'ready']
        );

        $concreteAgent = new class($this->agentModel, $this->sharedMemory) extends Agent {
            protected string $role = 'tester';

            public function think(array $input): array
            {
                return [];
            }

            public function act(array $plan): array
            {
                return [];
            }

            public function communicate(string $to, string $messageType, array $payload): void
            {
            }
        };

        // Read all messages
        $allMessages = $concreteAgent->readMessages();
        $this->assertNotEmpty($allMessages);

        // Read messages from planner specifically
        $plannerMessages = $concreteAgent->readMessages('planner');
        $this->assertNotEmpty($plannerMessages);
        
        $first = array_values($plannerMessages)[0];
        $this->assertEquals('planner', $first['from']);
    }

    public function test_agent_can_log_actions(): void
    {
        $concreteAgent = new class($this->agentModel, $this->sharedMemory) extends Agent {
            protected string $role = 'tester';

            public function think(array $input): array
            {
                return [];
            }

            public function act(array $plan): array
            {
                return [];
            }

            public function communicate(string $to, string $messageType, array $payload): void
            {
            }
        };

        $concreteAgent->log('task_started', ['task_id' => 5, 'priority' => 'high']);

        // Verify log exists in Redis
        $state = $this->sharedMemory->getSessionState($this->session->id);
        $this->assertNotEmpty($state['other'] ?? []); // Logs go to 'other' category
    }
}