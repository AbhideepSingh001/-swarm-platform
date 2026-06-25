<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExecuteTaskJob;
use App\Models\Task;
use Illuminate\Console\Command;

class ExecuteTaskCommand extends Command
{
    protected $signature = 'task:execute {task : Task ID to execute} {--queue : Dispatch to queue instead of running synchronously}';
    protected $description = 'Execute a task through the execution engine';

    public function handle(): int
    {
        $taskId = (int) $this->argument('task');
        $task = Task::find($taskId);

        if (!$task) {
            $this->error("Task {$taskId} not found.");
            return 1;
        }

        if ($task->status === 'running') {
            $this->warn("Task {$taskId} is already running.");
            return 1;
        }

        $this->info("Task {$taskId}: {$task->title}");
        $this->info("Driver: {$task->driver}");
        $this->info("Status: {$task->status}");

        if ($this->option('queue')) {
            ExecuteTaskJob::dispatch($task);
            $this->info("Dispatched to queue.");
        } else {
            $this->warn("Running synchronously...");
            $job = new ExecuteTaskJob($task);
            $job->handle(app(\App\Execution\Engine::class));
            $this->info("Done.");
        }

        return 0;
    }
}