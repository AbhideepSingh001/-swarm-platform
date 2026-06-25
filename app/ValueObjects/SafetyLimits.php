<?php

declare(strict_types=1);

namespace App\ValueObjects;

readonly class SafetyLimits
{
    public function __construct(
        public int $memoryMb = 512,
        public float $cpuPercent = 50.0,
        public int $maxOutputMb = 10,
        public bool $network = false,
        public int $timeoutSeconds = 30,
        public int $maxFileSizeMb = 100,
        public bool $allowFileWrite = false
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            memoryMb: $config['memory_mb'] ?? 512,
            cpuPercent: $config['cpu_percent'] ?? 50.0,
            maxOutputMb: $config['max_output_mb'] ?? 10,
            network: $config['network'] ?? false,
            timeoutSeconds: $config['timeout_seconds'] ?? 30,
            maxFileSizeMb: $config['max_file_size_mb'] ?? 100,
            allowFileWrite: $config['allow_file_write'] ?? false
        );
    }

    public function toDockerFlags(): array
    {
        $flags = [
            '--memory', "{$this->memoryMb}m",
            '--cpus', (string) ($this->cpuPercent / 100),
            '--network', $this->network ? 'bridge' : 'none',
            '--read-only',
        ];

        if (!$this->allowFileWrite) {
            $flags[] = '--tmpfs';
            $flags[] = '/tmp:noexec,nosuid,size=100m';
        }

        return $flags;
    }

    public function toArray(): array
    {
        return [
            'memory_mb' => $this->memoryMb,
            'cpu_percent' => $this->cpuPercent,
            'max_output_mb' => $this->maxOutputMb,
            'network' => $this->network,
            'timeout_seconds' => $this->timeoutSeconds,
            'max_file_size_mb' => $this->maxFileSizeMb,
            'allow_file_write' => $this->allowFileWrite,
        ];
    }
}