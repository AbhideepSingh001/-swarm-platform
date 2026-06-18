<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class SharedMemoryService
{
    /**
     * Key prefix to avoid collisions with other Redis data
     */
    private const PREFIX = 'swarm:';

    /**
     * Store data in Redis for a specific key
     * 
     * Example: store('session:5:agent:planner', ['task' => 'design db'])
     */
    public function store(string $key, array $data, ?int $ttl = null): void
    {
        $fullKey = self::PREFIX . $key;
        
        // Convert array to JSON string, then store in Redis
        Redis::set($fullKey, json_encode($data));
        
        // If TTL provided, set expiration (in seconds)
        if ($ttl !== null) {
            Redis::expire($fullKey, $ttl);
        }
    }

    /**
     * Retrieve data from Redis by key
     * 
     * Returns array or null if key doesn't exist
     */
    public function retrieve(string $key): ?array
    {
        $fullKey = self::PREFIX . $key;
        $value = Redis::get($fullKey);
        
        if ($value === null) {
            return null;
        }
        
        // Decode JSON back to PHP array
        return json_decode($value, true);
    }

    /**
     * Delete a key from Redis
     */
    public function delete(string $key): void
    {
        Redis::del(self::PREFIX . $key);
    }

    /**
     * Check if a key exists
     */
    public function exists(string $key): bool
    {
        return Redis::exists(self::PREFIX . $key) > 0;
    }

    /**
 * Get the full state of a session
 */
public function getSessionState(string $sessionId): array
{
    $pattern = self::PREFIX . "session:{$sessionId}:*";
    
    // Get all keys matching this session
    $keys = Redis::keys($pattern);
    
    $state = [
        'session_id' => $sessionId,
        'agents' => [],
        'tasks' => [],
        'messages' => [],
        'artifacts' => [],
        'timestamp' => now()->toDateTimeString(),
    ];
    
    foreach ($keys as $key) {
        // Redis::keys() returns keys WITH prefix already applied by Laravel
        // We need to strip the Laravel Redis prefix to get our internal key
        $cleanKey = str_replace(config('database.redis.options.prefix', ''), '', $key);
        
        // Now remove our swarm: prefix
        $cleanKey = str_replace(self::PREFIX, '', $cleanKey);
        
        $data = $this->retrieve($cleanKey);
        
        if ($data !== null) {
            if (str_contains($cleanKey, ':agent:')) {
                $state['agents'][$cleanKey] = $data;
            } elseif (str_contains($cleanKey, ':task:')) {
                $state['tasks'][$cleanKey] = $data;
            } elseif (str_contains($cleanKey, ':message:')) {
                $state['messages'][$cleanKey] = $data;
            } elseif (str_contains($cleanKey, ':artifact:')) {
                $state['artifacts'][$cleanKey] = $data;
            } else {
                $state['other'][$cleanKey] = $data;
            }
        }
    }
    
    return $state;
}

    /**
     * Store an agent's thought/memory
     * Shortcut method for agent self-reflection
     */
    public function storeAgentMemory(string $sessionId, string $agentRole, array $memory): void
    {
        $key = "session:{$sessionId}:agent:{$agentRole}:memory";
        $this->store($key, $memory);
    }

    /**
     * Retrieve an agent's memory
     */
    public function getAgentMemory(string $sessionId, string $agentRole): ?array
    {
        $key = "session:{$sessionId}:agent:{$agentRole}:memory";
        return $this->retrieve($key);
    }

    /**
     * Store a message between agents
     */
    public function storeMessage(string $sessionId, string $from, string $to, string $type, array $payload): void
    {
        $messageId = uniqid('msg_', true);
        $key = "session:{$sessionId}:message:{$messageId}";
        
        $this->store($key, [
            'id' => $messageId,
            'from' => $from,
            'to' => $to,
            'type' => $type,
            'payload' => $payload,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Store a task artifact (code, plan, etc.)
     */
    public function storeArtifact(string $sessionId, string $taskId, string $type, string $content): void
    {
        $key = "session:{$sessionId}:artifact:task:{$taskId}";
        
        $this->store($key, [
            'task_id' => $taskId,
            'type' => $type,
            'content' => $content,
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}