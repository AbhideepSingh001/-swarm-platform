<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Execution\Engine;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteTaskJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // We handle retries internally via RetryManager
    public int $timeout = 300; // 5 minutes max
    public ?string $uniqueFor = 600; // 10 minutes unique lock

    public function __construct(public Task $task) {}

    public function uniqueId(): string
    {
        return 'task-execution-' . $this->task->id;
    }

   public function handle(Engine $engine): void
{
    if ($this->task->status === 'cancelled') {
        Log::info("Task {$this->task->task_id} skipped — already cancelled");
        return;
    }

    $result = $engine->run($this->task);

    if (!$result->success && $this->task->retry_count < config('execution.max_attempts', 5)) {
        $delay = min(60, pow(2, $this->task->retry_count));
        self::dispatch($this->task->fresh())->delay(now()->addSeconds($delay));
    }
}

    public function failed(\Throwable $exception): void
    {
        Log::error("ExecuteTaskJob failed for task {$this->task->id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->task->update([
            'status' => 'failed',
            'error' => 'Job failed: ' . $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }
}