<?php

namespace App\Console\Commands;

use App\Services\AgentPresenceService;
use Illuminate\Console\Command;

class CleanupStalePresence extends Command
{
    protected $signature = 'presence:cleanup';
    protected $description = 'Mark stale agents as away or offline';

    public function handle(AgentPresenceService $service): void
    {
        $service->markAwayIfStale();
        $this->info('Stale presence cleaned up.');
    }
}