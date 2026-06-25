<?php

declare(strict_types=1);

namespace App\Execution\Drivers;

use App\Events\TaskOutputChunked;
use App\Models\Task;
use App\ValueObjects\ExecutionResult;
use App\Services\LLM\StreamingClient;
use Illuminate\Support\Facades\Log;

class LLMDriver extends AbstractDriver
{
    public function getName(): string
    {
        return 'llm';
    }

    public function validatePayload(array $payload): bool
    {
        $hasMessages = isset($payload['messages']) && is_array($payload['messages']);
        $hasPrompt = isset($payload['prompt']) && is_string($payload['prompt']);

        return isset($payload['model']) && is_string($payload['model'])
            && ($hasMessages || $hasPrompt);
    }

    public function execute(Task $task, array $config = []): ExecutionResult
    {
        $payload = $task->payload;
        $onProgress = $config['on_progress'] ?? null;

        return $this->withSafety(function () use ($payload, $onProgress, $task) {
            $client = app(StreamingClient::class);

            $model = $payload['model'];
            $messages = $payload['messages'] ?? [['role' => 'user', 'content' => $payload['prompt']]];
            $temperature = $payload['temperature'] ?? 0.7;
            $maxTokens = $payload['max_tokens'] ?? null;

            $output = '';
            $tokenCount = 0;
            $chunkCount = 0;

            $this->reportProgress(5, 'Initializing LLM connection', $onProgress);

            $stream = $client->stream(
                model: $model,
                messages: $messages,
                temperature: $temperature,
                maxTokens: $maxTokens
            );

            foreach ($stream as $chunk) {
                $output .= $chunk['content'] ?? '';
                $tokenCount += $chunk['token_count'] ?? 0;
                $chunkCount++;

                // Broadcast real-time chunk
                broadcast(new TaskOutputChunked(
                    taskId: $task->id,
                    chunk: $chunk['content'] ?? '',
                    tokenCount: $tokenCount
                ));

                // Progress based on token estimation (rough heuristic)
                $estimatedTotal = $maxTokens ?? 4096;
                $percent = min(95, (int) (($tokenCount / $estimatedTotal) * 100));
                $this->reportProgress($percent, "Streaming... {$tokenCount} tokens", $onProgress);

                // Safety: truncate if output exceeds limit
                if (strlen($output) > $this->safetyLimits->maxOutputMb * 1024 * 1024) {
                    Log::warning("LLM output truncated for task {$task->id}");
                    break;
                }
            }

            $this->reportProgress(100, 'Stream complete', $onProgress);

            return new ExecutionResult(
                success: true,
                output: $output,
                metadata: [
                    'model' => $model,
                    'tokens_output' => $tokenCount,
                    'chunks_received' => $chunkCount,
                    'temperature' => $temperature,
                ]
            );
        }, $onProgress);
    }
}