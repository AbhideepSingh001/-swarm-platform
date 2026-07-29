<?php

namespace App\Services\Swarm;

use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class FailureAnalyzer
{
    public const TIMEOUT = 'timeout';
    public const SYNTAX_ERROR = 'syntax_error';
    public const AGENT_ERROR = 'agent_error';
    public const NETWORK = 'network';
    public const UNKNOWN = 'unknown';

    public function categorize(?Throwable $exception): string
    {
        if ($exception === null) {
            return self::UNKNOWN;
        }

        $message = strtolower($exception->getMessage());
        $class = get_class($exception);

        // Timeout detection
        if ($this->isTimeout($message, $class)) {
            return self::TIMEOUT;
        }

        // Syntax / parsing errors
        if ($this->isSyntaxError($message, $class)) {
            return self::SYNTAX_ERROR;
        }

        // Network errors
        if ($this->isNetworkError($message, $class)) {
            return self::NETWORK;
        }

        // Agent-level errors (LLM API errors, validation failures, etc.)
        if ($this->isAgentError($message, $class, $exception)) {
            return self::AGENT_ERROR;
        }

        return self::UNKNOWN;
    }

    protected function isTimeout(string $message, string $class): bool
    {
        return Str::contains($message, [
            'timeout', 'timed out', 'execution time', 'max execution time',
            'curl timeout', 'connection timed out', 'read timeout',
        ]) || Str::contains($class, ['TimeoutException', 'MaxExecutionTimeError']);
    }

    protected function isSyntaxError(string $message, string $class): bool
    {
        return Str::contains($message, [
            'syntax error', 'parse error', 'unexpected token', 'invalid json',
            'json parse', 'yaml parse', 'malformed', 'invalid syntax',
        ]) || Str::contains($class, ['SyntaxError', 'ParseException', 'JsonException']);
    }

    protected function isNetworkError(string $message, string $class): bool
    {
        return Str::contains($message, [
            'connection refused', 'could not resolve host', 'network is unreachable',
            'dns lookup failed', 'ssl handshake', 'connection reset', 'errno',
        ]) || Str::contains($class, [
            'ConnectionException', 'NetworkException', 'ConnectException',
            'TransferException', 'RequestException',
        ]);
    }

    protected function isAgentError(string $message, string $class, Throwable $exception): bool
    {
        // HTTP 4xx/5xx from agent APIs
        if ($exception instanceof HttpException) {
            return true;
        }

        return Str::contains($message, [
            'rate limit', 'quota exceeded', 'invalid api key', 'unauthorized',
            'model not found', 'content policy', 'bad request', 'invalid request',
            'agent failed', 'tool execution failed', 'llm error',
        ]) || Str::contains($class, [
            'AgentException', 'LLMException', 'ApiException', 'OpenAIException',
            'AnthropicException', 'RateLimitException',
        ]);
    }
}
