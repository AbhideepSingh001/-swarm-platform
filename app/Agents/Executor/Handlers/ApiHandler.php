<?php

namespace App\Agents\Executor\Handlers;

use App\Agents\Executor\ExecutionResult;
use App\Agents\Executor\ExecutionTask;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiHandler implements TaskHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return $type === 'api_call';
    }

    public function getName(): string
    {
        return 'api_call';
    }

    public function execute(ExecutionTask $task): ExecutionResult
    {
        $start = microtime(true);
        $config = $task->config;

        try {
            $method = strtoupper($config['method'] ?? 'GET');
            $url = $config['url'] ?? '';
            $headers = $config['headers'] ?? [];
            $body = $config['body'] ?? null;
            $timeout = $config['timeout'] ?? 30;

            if (empty($url)) {
                return ExecutionResult::failure('No URL provided in task config');
            }

            $request = Http::timeout($timeout)->withHeaders($headers);

            if (!empty($config['api_key'])) {
                $request = $request->withToken($config['api_key']);
            }

            $response = match ($method) {
                'GET' => $request->get($url, $body),
                'POST' => $request->post($url, $body),
                'PUT' => $request->put($url, $body),
                'PATCH' => $request->patch($url, $body),
                'DELETE' => $request->delete($url, $body),
                default => $request->get($url, $body),
            };

            $time = round(microtime(true) - $start, 3);

            if ($response->successful()) {
                return ExecutionResult::success(
                    $response->body(),
                    [
                        'status_code' => $response->status(),
                        'headers' => $response->headers(),
                        'handler' => 'ApiHandler',
                    ],
                    $time
                );
            }

            return ExecutionResult::failure(
                "HTTP {$response->status()}: " . $response->body(),
                $response->status(),
                ['status_code' => $response->status(), 'handler' => 'ApiHandler'],
                $time
            );

        } catch (\Exception $e) {
            $time = round(microtime(true) - $start, 3);
            Log::error('API Handler Error', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            return ExecutionResult::failure($e->getMessage(), 500, ['handler' => 'ApiHandler'], $time);
        }
    }
}