<?php

namespace App\Agents\Executor\Handlers;

use App\Agents\Executor\ExecutionResult;
use App\Agents\Executor\ExecutionTask;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class CommandHandler implements TaskHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return $type === 'shell';
    }

    public function getName(): string
    {
        return 'shell';
    }

    public function execute(ExecutionTask $task): ExecutionResult
    {
        $start = microtime(true);
        $config = $task->config;

        try {
            $command = $config['command'] ?? '';
            $cwd = $config['cwd'] ?? base_path();
            $timeout = $config['timeout'] ?? 60;
            $env = $config['env'] ?? [];

            if (empty($command)) {
                return ExecutionResult::failure('No command provided in task config');
            }

            $allowedCommands = config('swarm.allowed_shell_commands', []);
            if (!empty($allowedCommands) && !$this->isAllowed($command, $allowedCommands)) {
                return ExecutionResult::failure('Command not in allowed whitelist');
            }

            $process = Process::fromShellCommandline($command, $cwd, $env, null, $timeout);
            $process->run();

            $time = round(microtime(true) - $start, 3);

            if ($process->isSuccessful()) {
                return ExecutionResult::success(
                    $process->getOutput(),
                    [
                        'exit_code' => $process->getExitCode(),
                        'command' => $command,
                        'handler' => 'CommandHandler',
                    ],
                    $time
                );
            }

            return ExecutionResult::failure(
                $process->getErrorOutput(),
                $process->getExitCode() ?? 1,
                ['command' => $command, 'handler' => 'CommandHandler'],
                $time
            );

        } catch (\Exception $e) {
            $time = round(microtime(true) - $start, 3);
            Log::error('Command Handler Error', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            return ExecutionResult::failure($e->getMessage(), 500, ['handler' => 'CommandHandler'], $time);
        }
    }

    private function isAllowed(string $command, array $allowed): bool
    {
        foreach ($allowed as $pattern) {
            if (str_starts_with($command, $pattern)) {
                return true;
            }
        }
        return false;
    }
}