<?php

declare(strict_types=1);

namespace App\Execution\Drivers;

use App\Contracts\ExecutionDriverInterface;
use App\Execution\Sandbox;
use App\Models\Task;
use App\ValueObjects\ExecutionResult;

class CodeDriver extends AbstractDriver
{
    private const ALLOWED_LANGUAGES = ['php', 'python', 'javascript', 'bash', 'ruby', 'go', 'rust'];

    public function getName(): string
    {
        return 'code';
    }

    public function validatePayload(array $payload): bool
    {
        return isset($payload['language'], $payload['source'])
            && is_string($payload['language'])
            && is_string($payload['source'])
            && in_array(strtolower($payload['language']), self::ALLOWED_LANGUAGES, true);
    }

    public function execute(Task $task, array $config = []): ExecutionResult
    {
        $payload = $task->payload;
        $sandbox = app(Sandbox::class);
        $onProgress = $config['on_progress'] ?? null;

        return $this->withSafety(function () use ($sandbox, $payload, $onProgress) {
            $this->reportProgress(10, 'Preparing sandbox environment', $onProgress);

            $language = strtolower($payload['language']);
            $source = $payload['source'];
            $inputs = $payload['inputs'] ?? [];

            $this->reportProgress(30, 'Executing in isolated container', $onProgress);

            $result = $sandbox->run(
                language: $language,
                code: $source,
                inputs: $inputs,
                timeout: $this->timeoutSeconds
            );

            $this->reportProgress(90, 'Processing results', $onProgress);

            return new ExecutionResult(
                success: $result->successful(),
                output: $result->output,
                error: $result->error,
                metadata: array_merge($result->toArray(), [
                    'language' => $language,
                    'input_count' => count($inputs),
                ])
            );
        }, $onProgress);
    }
}