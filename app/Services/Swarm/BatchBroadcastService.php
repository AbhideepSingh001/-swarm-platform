<?php

declare(strict_types=1);

namespace App\Services\Swarm;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

class BatchBroadcastService
{
    public function broadcastProgress(Batch $batch, string $event): void
    {
        $executionId = $batch->options['execution_id'] ?? 'unknown';
        $levelIndex = $batch->options['level_index'] ?? 0;
        $totalLevels = $batch->options['total_levels'] ?? 1;

        $progress = $this->calculateProgress($batch, $levelIndex, $totalLevels);

        $payload = [
            'execution_id' => $executionId,
            'event' => $event,
            'level' => $levelIndex,
            'total_levels' => $totalLevels,
            'progress_percent' => $progress['percent'],
            'completed_jobs' => $batch->processedJobs(),
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'failed_jobs' => $batch->failedJobs,
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
        ];

        Log::debug('BatchBroadcastService: broadcasting progress', $payload);

        try {
            Broadcast::event(new \App\Events\Swarm\WorkflowEvent(
                "batch.{$event}",
                $payload
            ));
        } catch (Throwable $e) {
            Log::debug('BatchBroadcastService: broadcast skipped', [
                'event' => $event,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    public function broadcastHeartbeat(string $executionId, int $levelIndex, array $stats): void
    {
        try {
            Broadcast::event(new \App\Events\Swarm\WorkflowEvent('batch.heartbeat', [
                'execution_id' => $executionId,
                'level' => $levelIndex,
                'stats' => $stats,
                'timestamp' => now()->toIso8601String(),
            ]));
        } catch (Throwable) {
            // Silent
        }
    }

    protected function calculateProgress(Batch $batch, int $levelIndex, int $totalLevels): array
    {
        if ($totalLevels === 0) {
            return ['percent' => 100, 'fraction' => 1.0];
        }

        $levelWeight = 1.0 / $totalLevels;
        $completedLevelsWeight = $levelIndex * $levelWeight;

        if ($batch->totalJobs === 0) {
            $currentLevelProgress = 0.0;
        } else {
            $currentLevelProgress = ($batch->processedJobs() / $batch->totalJobs) * $levelWeight;
        }

        $fraction = min($completedLevelsWeight + $currentLevelProgress, 1.0);
        $percent = (int) ($fraction * 100);

        return [
            'fraction' => round($fraction, 4),
            'percent' => $percent,
        ];
    }
}
