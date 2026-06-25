<?php

declare(strict_types=1);

namespace App\Execution;

use App\ValueObjects\SafetyLimits;
use App\ValueObjects\SandboxResult;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class Sandbox
{
    private array $config;
    private string $dockerNetwork;
    private string $defaultMemory;
    private string $defaultCpus;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->dockerNetwork = $config['docker_network'] ?? 'none';
        $this->defaultMemory = $config['default_memory'] ?? '512m';
        $this->defaultCpus = $config['default_cpus'] ?? '0.5';
    }

    /**
     * Run code in an isolated Docker container.
     */
    public function run(
        string $language,
        string $code,
        array $inputs = [],
        ?int $timeout = null,
        ?SafetyLimits $limits = null
    ): SandboxResult {
        $limits = $limits ?? new SafetyLimits();
        $timeout = $timeout ?? $limits->timeoutSeconds;

        $image = $this->resolveImage($language);
        $tempFile = $this->prepareCodeFile($language, $code);

        try {
            $process = $this->buildDockerProcess($image, $tempFile, $inputs, $limits, $timeout);
            $startTime = microtime(true);

            $process->run(function ($type, $buffer) use (&$stdout, &$stderr) {
                if ($type === Process::OUT) {
                    $stdout .= $buffer;
                } else {
                    $stderr .= $buffer;
                }
            });

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Parse memory stats if available
            $memoryPeak = $this->extractMemoryPeak($stderr);

            return new SandboxResult(
                exitCode: $process->getExitCode() ?? -1,
                output: $stdout ?? '',
                error: $stderr ?? '',
                durationMs: $duration,
                memoryPeakMb: $memoryPeak
            );

        } finally {
            // Cleanup temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Resolve Docker image for language.
     */
    private function resolveImage(string $language): string
    {
        $images = $this->config['images'] ?? [
            'php' => 'sandbox-php:8.3',
            'python' => 'sandbox-python:3.12',
            'javascript' => 'sandbox-node:20',
            'bash' => 'sandbox-alpine:latest',
            'ruby' => 'sandbox-ruby:3.3',
            'go' => 'sandbox-go:1.22',
            'rust' => 'sandbox-rust:1.78',
        ];

        if (!isset($images[$language])) {
            throw new \InvalidArgumentException("Unsupported sandbox language: {$language}");
        }

        return $images[$language];
    }

    /**
     * Prepare code file with proper extension and wrapper.
     */
    private function prepareCodeFile(string $language, string $code): string
    {
        $extensions = [
            'php' => '.php',
            'python' => '.py',
            'javascript' => '.js',
            'bash' => '.sh',
            'ruby' => '.rb',
            'go' => '.go',
            'rust' => '.rs',
        ];

        $ext = $extensions[$language] ?? '.txt';
        $tempFile = tempnam(sys_get_temp_dir(), 'swarm_sandbox_') . $ext;

        // Wrap code for stdin reading if inputs provided
        $wrappedCode = $this->wrapCode($language, $code);

        file_put_contents($tempFile, $wrappedCode);

        return $tempFile;
    }

    /**
     * Wrap code to handle stdin inputs safely.
     */
    private function wrapCode(string $language, string $code): string
    {
        return match ($language) {
            'php' => "<?php\n{$code}",
            'python' => $code, // Python handles stdin natively
            'javascript' => $code,
            'bash' => "#!/bin/sh\nset -e\n{$code}",
            'ruby' => $code,
            'go' => "package main\n{$code}",
            'rust' => "fn main() {\n{$code}\n}",
            default => $code,
        };
    }

    /**
     * Build the Docker process with safety flags.
     */
    private function buildDockerProcess(
        string $image,
        string $tempFile,
        array $inputs,
        SafetyLimits $limits,
        int $timeout
    ): Process {
        $dockerFlags = array_merge(
            [
                'docker', 'run', '--rm',
                '--network', $this->dockerNetwork,
                '--memory', "{$limits->memoryMb}m",
                '--cpus', (string) ($limits->cpuPercent / 100),
                '--read-only',
                '--pids-limit', '64',
                '--no-new-privileges',
                '--security-opt', 'no-new-privileges:true',
                '-v', "{$tempFile}:/app/script:ro",
                '-w', '/app',
                '-i', // Interactive for stdin
            ],
            $limits->toDockerFlags()
        );

        // Entry point varies by language
        $entryPoint = $this->resolveEntryPoint($image, $tempFile);

        $command = array_merge($dockerFlags, $entryPoint);

        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->setIdleTimeout($timeout);

        // Pass inputs via stdin
        if (!empty($inputs)) {
            $process->setInput(implode("\n", $inputs) . "\n");
        }

        return $process;
    }

    /**
     * Resolve entry point command for container.
     */
    private function resolveEntryPoint(string $image, string $tempFile): array
    {
        $basename = basename($tempFile);

        if (str_contains($image, 'php')) {
            return ['php', '/app/script'];
        }
        if (str_contains($image, 'python')) {
            return ['python3', '/app/script'];
        }
        if (str_contains($image, 'node')) {
            return ['node', '/app/script'];
        }
        if (str_contains($image, 'alpine')) {
            return ['sh', '/app/script'];
        }
        if (str_contains($image, 'ruby')) {
            return ['ruby', '/app/script'];
        }
        if (str_contains($image, 'go')) {
            // Go needs compilation step
            return ['sh', '-c', 'cd /app && go run script'];
        }
        if (str_contains($image, 'rust')) {
            return ['sh', '-c', 'cd /app && rustc script -o /tmp/out && /tmp/out'];
        }

        return ['/app/script'];
    }

    /**
     * Extract memory peak from cgroup stats in stderr.
     */
    private function extractMemoryPeak(string $stderr): ?float
    {
        // Look for memory usage patterns in stderr or parse /sys/fs/cgroup
        // This is a simplified version - in production you'd use docker stats API
        if (preg_match('/max_mem_used:\s*([\d.]+)\s*(KB|MB|GB)/i', $stderr, $matches)) {
            $value = (float) $matches[1];
            $unit = strtoupper($matches[2]);

            return match ($unit) {
                'KB' => $value / 1024,
                'MB' => $value,
                'GB' => $value * 1024,
                default => $value,
            };
        }

        return null;
    }

    /**
     * Check if Docker is available.
     */
    public function isAvailable(): bool
    {
        try {
            $process = new Process(['docker', 'info']);
            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            Log::warning('Docker not available for sandbox', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Pull required images.
     */
    public function pullImage(string $language): bool
    {
        $image = $this->resolveImage($language);

        try {
            $process = new Process(['docker', 'pull', $image]);
            $process->setTimeout(300);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            Log::error("Failed to pull sandbox image: {$image}", ['error' => $e->getMessage()]);
            return false;
        }
    }
}