<?php

declare(strict_types=1);

namespace Tests\Unit\Execution\Drivers;

use App\Execution\Drivers\CodeDriver;
use App\Execution\Sandbox;
use App\Models\Task;
use App\ValueObjects\SandboxResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CodeDriverTest extends TestCase
{
    use RefreshDatabase;

    private CodeDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new CodeDriver([
            'timeout' => 30,
            'max_retries' => 2,
            'safety_limits' => [
                'memory_mb' => 512,
                'cpu_percent' => 50,
                'max_output_mb' => 10,
            ],
        ]);
    }

    public function test_get_name_returns_code(): void
    {
        $this->assertSame('code', $this->driver->getName());
    }

    public function test_validate_payload_rejects_missing_language(): void
    {
        $this->assertFalse($this->driver->validatePayload(['source' => 'echo 1;']));
    }

    public function test_validate_payload_rejects_missing_source(): void
    {
        $this->assertFalse($this->driver->validatePayload(['language' => 'php']));
    }

    public function test_validate_payload_rejects_invalid_language(): void
    {
        $this->assertFalse($this->driver->validatePayload([
            'language' => 'cobol',
            'source' => 'DISPLAY "HELLO".',
        ]));
    }

    public function test_validate_payload_accepts_valid_php(): void
    {
        $this->assertTrue($this->driver->validatePayload([
            'language' => 'php',
            'source' => '<?php echo "hello";',
        ]));
    }

    public function test_validate_payload_accepts_valid_python(): void
    {
        $this->assertTrue($this->driver->validatePayload([
            'language' => 'python',
            'source' => 'print("hello")',
        ]));
    }

    public function test_get_required_resources(): void
    {
        $resources = $this->driver->getRequiredResources();

        $this->assertArrayHasKey('memory', $resources);
        $this->assertArrayHasKey('cpu', $resources);
        $this->assertArrayHasKey('timeout', $resources);
        $this->assertSame('512MB', $resources['memory']);
        $this->assertSame('50%', $resources['cpu']);
        $this->assertSame(30, $resources['timeout']);
    }

    public function test_get_max_retries(): void
    {
        $this->assertSame(2, $this->driver->getMaxRetries());
    }

    public function test_execute_truncates_excessive_output(): void
    {
        $task = Task::factory()->create([
            'driver' => 'code',
            'payload' => [
                'language' => 'php',
                'source' => '<?php echo str_repeat("x", 20 * 1024 * 1024);',
            ],
        ]);

        $sandbox = Mockery::mock(Sandbox::class);
        $sandbox->shouldReceive('run')->andReturn(new SandboxResult(
            exitCode: 0,
            output: str_repeat('x', 20 * 1024 * 1024),
            error: '',
            durationMs: 100,
            memoryPeakMb: 64
        ));

        $this->app->instance(Sandbox::class, $sandbox);

        $result = $this->driver->execute($task);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('[OUTPUT TRUNCATED', $result->output);
        $this->assertLessThan(11 * 1024 * 1024, strlen($result->output));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}