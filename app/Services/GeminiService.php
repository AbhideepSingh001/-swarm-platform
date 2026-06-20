<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Exceptions\LLMException;

class GeminiService
{
    private array $config;
    private array $apiKeys;
    private int $currentKeyIndex = 0;
    private array $keyStatus = [];
    private array $keyTiers = [];

    public function __construct()
    {
        $this->config = config('agents.llm.gemini');
        $this->apiKeys = array_filter($this->config['api_keys']);
        
        if (empty($this->apiKeys)) {
            throw new LLMException('No Gemini API keys configured. Set GEMINI_API_KEY_1, _2, or _3 in .env');
        }

        $this->keyTiers = $this->config['key_tiers'] ?? array_fill(0, count($this->apiKeys), 'free');

        foreach ($this->apiKeys as $index => $key) {
            $this->keyStatus[$index] = [
                'available' => true,
                'rate_limited_until' => null,
                'tier' => $this->keyTiers[$index] ?? 'free',
                'fail_count' => 0,
            ];
        }
    }

    public function decomposeGoal(string $goal, array $context = []): array
    {
        $cacheKey = 'plan:' . md5($goal . json_encode($context));
        
        if ($cached = Cache::get($cacheKey)) {
            Log::info('Planner cache hit', ['goal' => substr($goal, 0, 100)]);
            return $cached;
        }

        $prompt = $this->buildDecompositionPrompt($goal, $context);
        $response = $this->callGemini($prompt);
        $plan = $this->parsePlanResponse($response);
        $this->validatePlan($plan);
        
        Cache::put($cacheKey, $plan, config('agents.planner.cache_ttl', 3600));
        
        return $plan;
    }

    private function buildDecompositionPrompt(string $goal, array $context): string
    {
        $contextStr = empty($context) ? '' : "\nAdditional context: " . json_encode($context);
        
        return <<<PROMPT
You are a Planner Agent in a Multi-Agent Swarm Platform. Your job is to decompose a high-level goal into a structured, actionable plan.

GOAL: {$goal}{$contextStr}

INSTRUCTIONS:
1. Break the goal into 2-20 discrete sub-tasks
2. Each task must have: id, title, description, priority (low/medium/high/critical), estimated_duration_minutes
3. Identify dependencies between tasks (which tasks must complete before others can start)
4. Assign each task to the most appropriate agent type from: [researcher, coder, analyst, writer, reviewer, executor]
5. Return ONLY valid JSON in this exact structure:

{
  "plan_title": "Short descriptive title",
  "plan_description": "Brief overview of the plan",
  "tasks": [
    {
      "id": "task_1",
      "title": "Task name",
      "description": "What this task does",
      "priority": "high",
      "estimated_duration_minutes": 30,
      "agent_type": "coder",
      "depends_on": ["task_0"]
    }
  ],
  "metadata": {
    "total_tasks": 5,
    "estimated_total_minutes": 120,
    "complexity": "medium"
  }
}

RULES:
- Task IDs must be sequential: task_1, task_2, etc.
- "depends_on" can be empty [] for tasks with no dependencies
- A task can only depend on tasks with LOWER IDs (no circular dependencies)
- Priorities must be one of: low, medium, high, critical
- Agent types must be one of: researcher, coder, analyst, writer, reviewer, executor
- estimated_duration_minutes must be a realistic integer (5-480)
- Return ONLY the JSON object, no markdown, no explanations
PROMPT;
    }

    private function callGemini(string $prompt): array
    {
        $maxRetries = $this->config['max_retries'];
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $keyIndex = $this->getNextAvailableKey();
            
            if ($keyIndex === null) {
                throw new LLMException('All Gemini API keys are rate-limited. Please wait or add more keys.');
            }

            $apiKey = $this->apiKeys[$keyIndex];
            $model = $this->config['model'];
            $url = "{$this->config['base_url']}/{$model}:generateContent?key={$apiKey}";

            try {
                $response = Http::timeout($this->config['timeout'])
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'topP' => 0.8,
                            'topK' => 40,
                            'maxOutputTokens' => 8192,
                            'responseMimeType' => 'application/json',
                        ],
                        'safetySettings' => [
                            [
                                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                                'threshold' => 'BLOCK_ONLY_HIGH'
                            ],
                            [
                                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                                'threshold' => 'BLOCK_ONLY_HIGH'
                            ]
                        ]
                    ]);

                if ($response->successful()) {
                    $this->keyStatus[$keyIndex]['available'] = true;
                    $this->keyStatus[$keyIndex]['rate_limited_until'] = null;
                    return $response->json();
                }

                if ($response->status() === 429) {
                    $this->markKeyRateLimited($keyIndex);
                    Log::warning('Gemini API key rate limited', ['key_index' => $keyIndex, 'tier' => $this->keyStatus[$keyIndex]['tier']]);
                    continue;
                }

                $errorBody = $response->json();
                $errorMsg = $errorBody['error']['message'] ?? 'Unknown API error';
                throw new LLMException("Gemini API error: {$errorMsg}", $response->status());

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;
                Log::warning('Gemini connection failed, retrying...', ['attempt' => $attempt]);
                sleep($this->config['retry_delay'] * $attempt);
            } catch (LLMException $e) {
                throw $e;
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error('Gemini API unexpected error', ['error' => $e->getMessage()]);
            }
        }

        throw new LLMException(
            'Failed to call Gemini API after ' . $maxRetries . ' attempts. Last error: ' . ($lastException ? $lastException->getMessage() : 'Unknown'),
            0,
            $lastException
        );
    }

    private function getNextAvailableKey(): ?int
    {
        $now = time();
        $keyCount = count($this->apiKeys);
        
        // Phase 1: Try Pro keys first
        for ($i = 0; $i < $keyCount; $i++) {
            $index = ($this->currentKeyIndex + $i) % $keyCount;
            if ($this->keyStatus[$index]['tier'] === 'pro' && $this->isKeyAvailable($index, $now)) {
                $this->currentKeyIndex = ($index + 1) % $keyCount;
                return $index;
            }
        }
        
        // Phase 2: Fall back to Free keys
        for ($i = 0; $i < $keyCount; $i++) {
            $index = ($this->currentKeyIndex + $i) % $keyCount;
            if ($this->keyStatus[$index]['tier'] === 'free' && $this->isKeyAvailable($index, $now)) {
                $this->currentKeyIndex = ($index + 1) % $keyCount;
                return $index;
            }
        }
        
        return null;
    }

    private function isKeyAvailable(int $index, int $now): bool
    {
        if (!$this->keyStatus[$index]['available']) {
            $limitedUntil = $this->keyStatus[$index]['rate_limited_until'];
            if ($limitedUntil !== null && $now > $limitedUntil) {
                $this->keyStatus[$index]['available'] = true;
                $this->keyStatus[$index]['rate_limited_until'] = null;
                return true;
            }
            return false;
        }
        return true;
    }

    private function markKeyRateLimited(int $keyIndex): void
    {
        $tier = $this->keyStatus[$keyIndex]['tier'];
        $cooldown = ($tier === 'pro') ? 30 : 120;
        
        $this->keyStatus[$keyIndex]['available'] = false;
        $this->keyStatus[$keyIndex]['rate_limited_until'] = time() + $cooldown;
        $this->keyStatus[$keyIndex]['fail_count']++;
        
        Log::warning("Gemini API key rate limited", [
            'key_index' => $keyIndex,
            'tier' => $tier,
            'cooldown_seconds' => $cooldown,
        ]);
    }

    private function parsePlanResponse(array $response): array
    {
        $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        
        if (!$content) {
            $content = $response['text'] ?? null;
        }
        
        if (!$content) {
            throw new LLMException('Empty response from Gemini API');
        }

        $content = trim($content);
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        
        $plan = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse Gemini JSON response', ['content' => substr($content, 0, 500)]);
            throw new LLMException('Invalid JSON in Gemini response: ' . json_last_error_msg());
        }
        
        return $plan;
    }

    private function validatePlan(array $plan): void
    {
        $requiredTopLevel = ['plan_title', 'plan_description', 'tasks', 'metadata'];
        foreach ($requiredTopLevel as $key) {
            if (!isset($plan[$key])) {
                throw new LLMException("Missing required plan field: {$key}");
            }
        }

        if (!is_array($plan['tasks']) || empty($plan['tasks'])) {
            throw new LLMException('Plan must contain at least one task');
        }

        if (count($plan['tasks']) > config('agents.planner.max_tasks_per_plan', 50)) {
            throw new LLMException('Plan exceeds maximum task limit');
        }

        $taskIds = [];
        $validPriorities = config('agents.planner.allowed_priorities', ['low', 'medium', 'high', 'critical']);
        $validAgents = ['researcher', 'coder', 'analyst', 'writer', 'reviewer', 'executor'];

        foreach ($plan['tasks'] as $index => $task) {
            $requiredTaskFields = ['id', 'title', 'description', 'priority', 'estimated_duration_minutes', 'agent_type', 'depends_on'];
            foreach ($requiredTaskFields as $field) {
                if (!isset($task[$field])) {
                    throw new LLMException("Task {$index} missing field: {$field}");
                }
            }

            if (!in_array($task['priority'], $validPriorities)) {
                throw new LLMException("Task {$task['id']} has invalid priority: {$task['priority']}");
            }

            if (!in_array($task['agent_type'], $validAgents)) {
                throw new LLMException("Task {$task['id']} has invalid agent_type: {$task['agent_type']}");
            }

            if (!is_int($task['estimated_duration_minutes']) || $task['estimated_duration_minutes'] < 1 || $task['estimated_duration_minutes'] > 480) {
                throw new LLMException("Task {$task['id']} has invalid duration");
            }

            if (!is_array($task['depends_on'])) {
                throw new LLMException("Task {$task['id']} depends_on must be an array");
            }

            $taskIds[] = $task['id'];
        }

        foreach ($plan['tasks'] as $task) {
            foreach ($task['depends_on'] as $dep) {
                if (!in_array($dep, $taskIds)) {
                    throw new LLMException("Task {$task['id']} depends on non-existent task: {$dep}");
                }
            }
        }
    }

    public function getKeyStatus(): array
    {
        return $this->keyStatus;
    }

    public function clearRateLimits(): void
    {
        foreach ($this->keyStatus as $index => $status) {
            $this->keyStatus[$index]['available'] = true;
            $this->keyStatus[$index]['rate_limited_until'] = null;
        }
    }
}