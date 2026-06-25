<?php

namespace App\Services;

use App\Events\AgentPresenceUpdated;
use App\Events\AgentWentOffline;
use App\Events\AgentWentOnline;
use App\Models\Agent;
use App\Models\AgentPresence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AgentPresenceService
{
    private const HEARTBEAT_TTL_SECONDS = 60;
    private const AWAY_THRESHOLD_SECONDS = 120;

    public function comeOnline(Agent $agent, ?string $channel = null, ?array $metadata = null): AgentPresence
    {
        $presence = AgentPresence::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'status' => 'online',
                'current_channel' => $channel,
                'last_seen_at' => now(),
                'metadata' => $metadata,
            ]
        );

        Cache::put(
            "agent:{$agent->id}:heartbeat",
            now()->timestamp,
            self::HEARTBEAT_TTL_SECONDS
        );

        broadcast(new AgentWentOnline($agent))->toOthers();
        broadcast(new AgentPresenceUpdated($presence))->toOthers();

        Log::info('Agent came online', ['agent_id' => $agent->id, 'channel' => $channel]);

        return $presence;
    }

    public function goOffline(Agent $agent): void
    {
        $presence = AgentPresence::where('agent_id', $agent->id)->first();

        if ($presence) {
            $presence->update([
                'status' => 'offline',
                'current_channel' => null,
                'last_seen_at' => now(),
            ]);

            Cache::forget("agent:{$agent->id}:heartbeat");

            broadcast(new AgentWentOffline($agent))->toOthers();
            broadcast(new AgentPresenceUpdated($presence))->toOthers();

            Log::info('Agent went offline', ['agent_id' => $agent->id]);
        }
    }

    public function heartbeat(Agent $agent, ?string $channel = null, ?array $metadata = null): AgentPresence
    {
        $presence = AgentPresence::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'status' => 'online',
                'current_channel' => $channel,
                'last_seen_at' => now(),
                'metadata' => $metadata,
            ]
        );

        Cache::put(
            "agent:{$agent->id}:heartbeat",
            now()->timestamp,
            self::HEARTBEAT_TTL_SECONDS
        );

        return $presence;
    }

    public function markAwayIfStale(): void
    {
        $threshold = now()->subSeconds(self::AWAY_THRESHOLD_SECONDS);

        AgentPresence::where('status', 'online')
            ->where('last_seen_at', '<', $threshold)
            ->update(['status' => 'away']);

        $offlineThreshold = now()->subMinutes(5);

        AgentPresence::whereIn('status', ['online', 'away'])
            ->where('last_seen_at', '<', $offlineThreshold)
            ->each(function (AgentPresence $presence) {
                $this->goOffline($presence->agent);
            });
    }

    public function getOnlineAgents(): array
    {
        return AgentPresence::where('status', 'online')
            ->with('agent:id,name,role')
            ->get()
            ->map(fn ($p) => [
                'agent_id' => $p->agent_id,
                'name' => $p->agent->name,
                'role' => $p->agent->role,
                'current_channel' => $p->current_channel,
                'last_seen_at' => $p->last_seen_at->toIso8601String(),
                'metadata' => $p->metadata,
            ])
            ->toArray();
    }

    public function isAgentOnline(int $agentId): bool
    {
        $lastHeartbeat = Cache::get("agent:{$agentId}:heartbeat");

        if (!$lastHeartbeat) {
            return false;
        }

        return (now()->timestamp - $lastHeartbeat) < self::HEARTBEAT_TTL_SECONDS;
    }
}