<?php

namespace App\Agents\Executor;

use App\Models\TaskExecution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResultReporter
{
    public function report(ExecutionTask $task, TaskExecution $execution): void
    {
        if (!empty($task->callbackUrl)) {
            $this->sendWebhook($task, $execution);
        }

        if ($execution->status === 'retrying') {
            dispatch(new \App\Jobs\ExecuteTaskJob($task->id))->delay(now()->addSeconds(5));
        }

        Log::info('Task execution reported', [
            'task_id' => $task->id,
            'execution_id' => $execution->id,
            'status' => $execution->status,
        ]);
    }

    private function sendWebhook(ExecutionTask $task, TaskExecution $execution): void
    {
        try {
            Http::timeout(10)->post($task->callbackUrl, [
                'task_id' => $task->id,
                'execution_id' => $execution->id,
                'status' => $execution->status,
                'output' => $execution->output,
                'error' => $execution->error,
                'metadata' => $execution->metadata,
                'attempt' => $execution->attempt,
            ]);
        } catch (\Exception $e) {
            Log::warning('Webhook delivery failed', [
                'task_id' => $task->id,
                'callback_url' => $task->callbackUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}