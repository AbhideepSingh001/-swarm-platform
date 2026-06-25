<?php

declare(strict_types=1);

namespace App\Execution\Drivers;

use App\Models\Task;
use App\ValueObjects\ExecutionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HTTPDriver extends AbstractDriver
{
    private array $allowedHosts;
    private bool $whitelistMode;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->allowedHosts = $config['allowed_hosts'] ?? [];
        $this->whitelistMode = !empty($this->allowedHosts);
    }

    public function getName(): string
    {
        return 'http';
    }

    public function validatePayload(array $payload): bool
    {
        return isset($payload['url']) && is_string($payload['url'])
            && isset($payload['method']) && is_string($payload['method'])
            && in_array(strtoupper($payload['method']), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true);
    }

    public function execute(Task $task, array $config = []): ExecutionResult
    {
        $payload = $task->payload;
        $onProgress = $config['on_progress'] ?? null;

        return $this->withSafety(function () use ($payload, $onProgress) {
            $url = $payload['url'];
            $method = strtoupper($payload['method']);
            $headers = $payload['headers'] ?? [];
            $body = $payload['body'] ?? null;

            if (!$this->isHostAllowed($url)) {
                $host = parse_url($url, PHP_URL_HOST) ?? 'unknown';
                Log::warning('HTTP request to non-allowed host blocked', ['host' => $host, 'url' => $url]);
                return new ExecutionResult(
                    success: false,
                    error: "Host [{$host}] is not in the allowlist. Contact admin to add it."
                );
            }

            $this->reportProgress(20, 'Sending HTTP request', $onProgress);

            $request = Http::timeout($this->timeoutSeconds)
                ->withHeaders($headers);

            // Add retry on Laravel HTTP client side for transient failures
            if ($this->maxRetries > 1) {
                $request->retry($this->maxRetries, 100);
            }

            $response = match ($method) {
                'GET' => $request->get($url),
                'POST' => $request->post($url, $body),
                'PUT' => $request->put($url, $body),
                'PATCH' => $request->patch($url, $body),
                'DELETE' => $request->delete($url, $body),
                'HEAD' => $request->head($url),
                'OPTIONS' => $request->send('OPTIONS', $url),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}")
            };

            $this->reportProgress(100, 'Response received', $onProgress);

            return new ExecutionResult(
                success: $response->successful(),
                output: $response->body(),
                metadata: [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'url' => $url,
                    'method' => $method,
                    'response_time_ms' => $response->handlerStats()['total_time'] ?? null,
                ]
            );
        }, $onProgress);
    }

    private function isHostAllowed(string $url): bool
    {
        if (!$this->whitelistMode) {
            return true; // Allow all if no whitelist configured
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        foreach ($this->allowedHosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, ".{$allowed}")) {
                return true;
            }
        }

        return false;
    }
}