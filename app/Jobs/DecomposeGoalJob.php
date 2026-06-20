<?php

namespace App\Jobs;

use App\Services\PlannerAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DecomposeGoalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $goal;
    public array $context;
    public ?string $userId;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(string $goal, array $context = [], ?string $userId = null)
    {
        $this->goal = $goal;
        $this->context = $context;
        $this->userId = $userId;
    }

    public function handle(PlannerAgent $planner): void
    {
        Log::info('Decomposing goal', ['goal' => substr($this->goal, 0, 100)]);

        $plan = $planner->createPlan($this->goal, $this->context, $this->userId);

        Log::info('Plan created from job', [
            'plan_id' => $plan->id,
            'task_count' => $plan->tasks->count(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DecomposeGoalJob failed', [
            'goal' => substr($this->goal, 0, 100),
            'error' => $exception->getMessage(),
        ]);
    }
}