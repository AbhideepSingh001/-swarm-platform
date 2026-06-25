<?php

declare(strict_types=1);

namespace App\Execution\Sandbox;

use App\ValueObjects\SandboxResult;
use App\ValueObjects\SafetyLimits;
use Symfony\Component\Process\Process;

/**
 * Fallback sandbox using Symfony Process directly (less isolated).
 * Use only when Docker is unavailable.
 */
class ProcessSandbox
{
    public function run(
        string $language,
        string $code,
        array $inputs = [],
        ?int $timeout = null,
        ?SafetyLimits $limits = null
    ): SandboxResult {
        $limits = $limits ?? new SafetyLimits();
        $timeout = $timeout ?? $limits->timeoutSeconds;

        $interpreter = match ($language) {
            'php' => ['php'],
            'python' => ['python3'],
            'javascript' => ['node'],
            'bash' => ['bash'],
            'ruby' => ['ruby'],
            default => throw new \InvalidArgumentException("No interpreter available for: {$language}")
        };

        $tempFile = tempnam(sys_get_temp_dir(), 'swarm_proc_') . match ($language) {
            'php' => '.php',
            'python' => '.py',
            'javascript' => '.js',
            'bash' => '.sh',
            'ruby' => '.rb',
            default => '.txt'
        };

        file_put_contents($tempFile, $code);

        try {
            $process = new Process(array_merge($interpreter, [$tempFile]));
            $process->setTimeout($timeout);

            if (!empty($inputs)) {
                $process->setInput(implode("\n", $inputs) . "\n");
            }

            $startTime = microtime(true);
            $process->run();

            return new SandboxResult(
                exitCode: $process->getExitCode() ?? -1,
                output: $process->getOutput(),
                error: $process->getErrorOutput(),
                durationMs: round((microtime(true) - $startTime) * 1000, 2),
                memoryPeakMb: null
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}