<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\AgentPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentPresenceController extends Controller
{
    public function __construct(
        private AgentPresenceService $presenceService
    ) {}

    public function online(Request $request, int $agentId): JsonResponse
    {
        $agent = Agent::findOrFail($agentId);

        $presence = $this->presenceService->comeOnline(
            $agent,
            $request->input('channel'),
            $request->input('metadata')
        );

        return response()->json([
            'success' => true,
            'presence' => $presence,
        ]);
    }

    public function offline(Request $request, int $agentId): JsonResponse
    {
        $agent = Agent::findOrFail($agentId);

        $this->presenceService->goOffline($agent);

        return response()->json([
            'success' => true,
            'message' => 'Agent marked offline',
        ]);
    }

    public function heartbeat(Request $request, int $agentId): JsonResponse
    {
        $agent = Agent::findOrFail($agentId);

        $presence = $this->presenceService->heartbeat(
            $agent,
            $request->input('channel'),
            $request->input('metadata')
        );

        return response()->json([
            'success' => true,
            'presence' => $presence,
        ]);
    }

    public function status(int $agentId): JsonResponse
    {
        $agent = Agent::findOrFail($agentId);

        $presence = $agent->presence;
        $isOnline = $presence ? $presence->isOnline() : false;

        return response()->json([
            'agent_id' => $agentId,
            'status' => $presence?->status ?? 'offline',
            'is_online' => $isOnline,
            'last_seen_at' => $presence?->last_seen_at?->toIso8601String(),
            'current_channel' => $presence?->current_channel,
        ]);
    }

    public function allOnline(): JsonResponse
    {
        return response()->json([
            'online_agents' => $this->presenceService->getOnlineAgents(),
            'count' => count($this->presenceService->getOnlineAgents()),
        ]);
    }
}