<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\AgentPresenceService;
use Illuminate\Console\Command;

class TestAgentPresence extends Command
{
    protected $signature = 'presence:test 
                            {action : online|offline|heartbeat|status|all-online} 
                            {agentId? : Agent ID (required for online, offline, heartbeat, status)}
                            {--channel= : Channel name for online/heartbeat}
                            {--metadata= : JSON metadata for online/heartbeat}';

    protected $description = 'Test AgentPresence service directly';

    public function handle(AgentPresenceService $service): int
    {
        $action = $this->argument('action');
        $agentId = $this->argument('agentId');

        switch ($action) {
            case 'online':
                if (!$agentId) {
                    $this->error('agentId is required for "online" action');
                    return 1;
                }
                $agent = Agent::find($agentId);
                if (!$agent) {
                    $this->error("Agent {$agentId} not found");
                    return 1;
                }
                $metadata = $this->option('metadata') ? json_decode($this->option('metadata'), true) : null;
                $presence = $service->comeOnline($agent, $this->option('channel'), $metadata);
                $this->info("Agent {$agentId} is now ONLINE");
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Status', $presence->status],
                        ['Channel', $presence->current_channel ?? 'null'],
                        ['Last Seen', $presence->last_seen_at],
                        ['Metadata', json_encode($presence->metadata)],
                    ]
                );
                break;

            case 'offline':
                if (!$agentId) {
                    $this->error('agentId is required for "offline" action');
                    return 1;
                }
                $agent = Agent::find($agentId);
                if (!$agent) {
                    $this->error("Agent {$agentId} not found");
                    return 1;
                }
                $service->goOffline($agent);
                $this->info("Agent {$agentId} is now OFFLINE");
                break;

            case 'heartbeat':
                if (!$agentId) {
                    $this->error('agentId is required for "heartbeat" action');
                    return 1;
                }
                $agent = Agent::find($agentId);
                if (!$agent) {
                    $this->error("Agent {$agentId} not found");
                    return 1;
                }
                $metadata = $this->option('metadata') ? json_decode($this->option('metadata'), true) : null;
                $presence = $service->heartbeat($agent, $this->option('channel'), $metadata);
                $this->info("Heartbeat sent for Agent {$agentId}");
                $this->line("Status: {$presence->status} | Channel: " . ($presence->current_channel ?? 'null'));
                break;

            case 'status':
                if (!$agentId) {
                    $this->error('agentId is required for "status" action');
                    return 1;
                }
                $agent = Agent::find($agentId);
                if (!$agent) {
                    $this->error("Agent {$agentId} not found");
                    return 1;
                }
                $presence = $agent->presence;
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Agent ID', $agentId],
                        ['Status', $presence?->status ?? 'offline'],
                        ['Is Online', $presence?->isOnline() ? 'Yes' : 'No'],
                        ['Last Seen', $presence?->last_seen_at?->toDateTimeString() ?? 'Never'],
                        ['Current Channel', $presence?->current_channel ?? 'null'],
                    ]
                );
                break;

            case 'all-online':
                $agents = $service->getOnlineAgents();
                if (empty($agents)) {
                    $this->warn('No agents are currently online');
                    return 0;
                }
                $this->info(count($agents) . ' agent(s) online:');
                $this->table(
                    ['Agent ID', 'Name', 'Role', 'Channel', 'Last Seen'],
                    collect($agents)->map(fn($a) => [
                        $a['agent_id'],
                        $a['name'],
                        $a['role'],
                        $a['current_channel'] ?? 'null',
                        $a['last_seen_at'],
                    ])->toArray()
                );
                break;

            default:
                $this->error("Unknown action: {$action}");
                $this->line('Available actions: online, offline, heartbeat, status, all-online');
                return 1;
        }

        return 0;
    }
}