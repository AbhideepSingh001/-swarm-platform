<?php

declare(strict_types=1);

namespace App\Execution\Drivers;

use App\Models\Task;
use App\ValueObjects\ExecutionResult;
use Illuminate\Support\Facades\Process;

class ShellDriver extends AbstractDriver
{
    private array $blockedPatterns = [
        'rm\s+-rf\s+\/',
        'mkfs\.',
        'dd\s+if=',
        '>:?\s*\/dev\/',
        'curl\s+',
        'wget\s+',
        'nc\s+',
        'netcat',
        'bash\s+-i',
        '\/dev\/tcp\/',
        '\/dev\/udp\/',
        'python\s+-c\s+.*import\s+socket',
        'python3\s+-c\s+.*import\s+socket',
        'ncat',
        'socat',
        'telnet',
        'ssh\s+.*@',
        'scp\s+',
        'rsync\s+.*:',
        '>\s*\/etc\/',
        '>\s*\/var\/',
        'chmod\s+.*777',
        'chmod\s+.*\+s',
        'chown\s+.*root',
        'sudo\s+',
        'su\s+-',
    ];

    public function getName(): string
    {
        return 'shell';
    }

    public function validatePayload(array $payload): bool
    {
        return isset($payload['command']) && is_string($payload['command']) && !empty($payload['command']);
    }

    public function execute(Task $task, array $config = []): ExecutionResult
    {
        $command = $task->payload['command'] ?? ($task->config['command'] ?? '');
        
        if (empty($command)) {
            return new ExecutionResult(success: false, error: 'No command provided');
        }

        $onProgress = $config['on_progress'] ?? null;

        return $this->withSafety(function () use ($command, $onProgress) {
            if ($this->isBlocked($command)) {
                return new ExecutionResult(
                    success: false,
                    error: 'Command blocked by safety policy. Contact admin if this is a false positive.'
                );
            }

            $this->reportProgress(10, 'Preparing shell execution', $onProgress);

            $result = Process::timeout($this->timeoutSeconds)
                ->run($command);

            $this->reportProgress(100, 'Execution complete', $onProgress);

            return new ExecutionResult(
                success: $result->successful(),
                output: $result->output(),
                error: $result->errorOutput(),
                metadata: [
                    'exit_code' => $result->exitCode(),
                    'command' => $command,
                    'working_directory' => getcwd(),
                ]
            );
        }, $onProgress);
    }

    private function isBlocked(string $command): bool
    {
        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match("/{$pattern}/i", $command)) {
                return true;
            }
        }
        return false;
    }
}