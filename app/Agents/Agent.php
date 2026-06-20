<?php

namespace App\Agents;

use App\Models\Agent as AgentModel;
use App\Services\SharedMemoryService;

abstract class Agent
{
    /**
     * The agent's role in the swarm
     * Examples: planner, coder, critic, researcher, executor
     */
    protected string $role;

    /**
     * The AI model used by this agent (nullable for now, real in Phase 4)
     */
    protected ?string $model = null;

    /**
     * The agent's working memory — temporary state during execution
     */
    protected array $memory = [];

    /**
     * The database model representing this agent
     */
    protected AgentModel $dbModel;

    /**
     * Shared memory service for inter-agent communication
     */
    protected SharedMemoryService $sharedMemory;

    /**
     * Current swarm session ID
     */
    protected int $sessionId;

    /**
     * Constructor — called when any agent is created
     */
    public function __construct(AgentModel $dbModel, SharedMemoryService $sharedMemory)
    {
        $this->dbModel = $dbModel;
        $this->sharedMemory = $sharedMemory;
        $this->sessionId = $dbModel->session_id;
        
        // Load any existing memory from Redis
        $this->loadMemory();
    }

    /**
     * ABSTRACT: Every agent MUST implement how it thinks
     * 
     * Think = analyze input, reason, plan next steps
     * Returns: array of insights, decisions, or plans
     */
    abstract public function think(array $input): array;

    /**
     * ABSTRACT: Every agent MUST implement how it acts
     * 
     * Act = execute the plan (write code, review, research, etc.)
     * Returns: array containing the result/output
     */
    abstract public function act(array $plan): array;

    /**
     * ABSTRACT: Every agent MUST implement how it communicates
     * 
     * Communicate = send messages to other agents
     * Returns: void (just sends, no return needed)
     */
    abstract public function communicate(string $to, string $messageType, array $payload): void;

    /**
     * Get the agent's role
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Get the agent's name
     */
    public function getName(): string
    {
        return $this->dbModel->name;
    }

    /**
     * Get the agent's current status
     */
    public function getStatus(): string
    {
        return $this->dbModel->status;
    }

    /**
     * Update the agent's status in database
     */
    public function setStatus(string $status): void
    {
        $this->dbModel->update(['status' => $status]);
    }

    /**
     * Save current memory to Redis
     */
    public function saveMemory(): void
    {
        $this->sharedMemory->storeAgentMemory(
            $this->sessionId,
            $this->role,
            $this->memory
        );
    }

    /**
     * Load memory from Redis
     */
    protected function loadMemory(): void
    {
        $stored = $this->sharedMemory->getAgentMemory($this->sessionId, $this->role);
        
        if ($stored !== null) {
            $this->memory = $stored;
        }
    }

    /**
     * Get a value from memory
     */
    public function recall(string $key): mixed
    {
        return $this->memory[$key] ?? null;
    }

    /**
     * Store a value in memory
     */
    public function remember(string $key, mixed $value): void
    {
        $this->memory[$key] = $value;
        $this->saveMemory(); // Auto-save to Redis
    }

    /**
     * Read a message from another agent
     * (Convenience method for checking shared memory)
     */
    public function readMessages(?string $from = null): array
    {
        $state = $this->sharedMemory->getSessionState($this->sessionId);
        $messages = $state['messages'] ?? [];
        
        if ($from === null) {
            return $messages; // All messages
        }
        
        // Filter messages from specific agent
        return array_filter($messages, function ($msg) use ($from) {
            return ($msg['from'] ?? '') === $from;
        });
    }

    /**
     * Log an action for debugging
     */
    public function log(string $action, array $details = []): void
    {
        $this->sharedMemory->store(
            "session:{$this->sessionId}:log:agent:{$this->role}:" . uniqid(),
            [
                'agent' => $this->role,
                'action' => $action,
                'details' => $details,
                'timestamp' => now()->toDateTimeString(),
            ]
        );
    }
}