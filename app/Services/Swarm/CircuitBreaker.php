<?php

namespace App\Services\Swarm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreaker
{
    protected string $prefix = 'swarm:circuit_breaker:';

    public function recordFailure(string $agentId): void
    {
        $key = $this->failureKey($agentId);
        $failures = Cache::increment($key);

        if ($failures === 1) {
            Cache::put($key, 1, now()->addMinutes(5));
        }

        $threshold = config('swarm.circuit_breaker.threshold', 5);

        if ($failures >= $threshold) {
            $this->trip($agentId);
        }

        Log::warning('Circuit breaker failure recorded', [
            'agent_id' => $agentId,
            'failures' => $failures,
            'threshold' => $threshold,
        ]);
    }

    public function recordSuccess(string $agentId): void
    {
        $key = $this->failureKey($agentId);
        Cache::forget($key);
        Cache::forget($this->trippedKey($agentId));
    }

    public function isOpen(string $agentId): bool
    {
        return Cache::has($this->trippedKey($agentId));
    }

    public function isHalfOpen(string $agentId): bool
    {
        return false;
    }

    public function trip(string $agentId): void
    {
        $cooldown = config('swarm.circuit_breaker.cooldown_minutes', 5);
        Cache::put($this->trippedKey($agentId), true, now()->addMinutes($cooldown));

        Log::error('Circuit breaker tripped', [
            'agent_id' => $agentId,
            'cooldown_minutes' => $cooldown,
        ]);
    }

    public function reset(string $agentId): void
    {
        Cache::forget($this->failureKey($agentId));
        Cache::forget($this->trippedKey($agentId));

        Log::info('Circuit breaker reset', ['agent_id' => $agentId]);
    }

    public function status(string $agentId): array
    {
        return [
            'agent_id' => $agentId,
            'is_open' => $this->isOpen($agentId),
            'failure_count' => (int) Cache::get($this->failureKey($agentId), 0),
        ];
    }

    protected function failureKey(string $agentId): string
    {
        return $this->prefix . 'failures:' . $agentId;
    }

    protected function trippedKey(string $agentId): string
    {
        return $this->prefix . 'tripped:' . $agentId;
    }
}
