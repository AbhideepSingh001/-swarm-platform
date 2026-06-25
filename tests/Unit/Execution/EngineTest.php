<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use App\Events\TaskStarted;
use App\Events\TaskCompleted;
use App\Events\TaskFailed;
use App\Execution\DriverRegistry;
use App\Execution\Engine;
use App\Execution\RetryManager;
use App\Execution\Sandbox;
use App\Models\Task;
use App\ValueObjects\ExecutionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class EngineTest extends TestCase
{
    use RefreshDatabase;

    private Engine $engine;
    private $registry;
    private $retryManager;
    private $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->registry = Mockery::mock(DriverRegistry::class);
        $this->retryManager = Mockery::mock(RetryManager::class);
        $this->sandbox = Mockery::mock(Sandbox::class);

        // Bind mocks to container
        $this->app->instance(DriverRegistry::class, $this->registry);
        $this->app->instance(RetryManager::class, $this->retryManager);
        $this->app->instance(Sandbox::class, $this->sandbox);

        $this->engine = new Engine($this->registry, $this->retryManager, $this->sandbox);
    }

    public function test_runs_task_and_broadcasts_events(): void
    {
        $task = Task::factory()->create([
            'driver' => 'code',
            'payload' => ['language' => 'php', 'source' => '<?php echo 1;'],
            'status' => 'pending',
        ]);

        $driver = Mockery::mock(\App\Contracts\ExecutionDriverInterface::class);
        $driver->shouldReceive('validatePayload')->andReturn(true);
        $driver->shouldReceive('execute')->andReturn(new ExecutionResult(
            success: true,
            output: '1',
            metadata: ['duration_ms' => 100]
        ));
        $driver->shouldReceive('getMaxRetries')->andReturn(2);

        $this->registry->shouldReceive('get')->with('code')->andReturn($driver);
        $this->retryManager->shouldReceive('executeWithRetry')->andReturnUsing(
            fn($task, $callback) => $callback()
        );

        $result = $this->engine->run($task);

        $this->assertTrue($result->success);
        $this->assertSame('1', $result->output);

        Event::assertDispatched(TaskStarted::class);
        Event::assertDispatched(TaskCompleted::class);
    }

    public function test_fails_on_invalid_payload(): void
    {
        $task = Task::factory()->create([
            'driver' => 'code',
            'payload' => ['invalid' => true],
        ]);

        $driver = Mockery::mock(\App\Contracts\ExecutionDriverInterface::class);
        $driver->shouldReceive('validatePayload')->andReturn(false);

        $this->registry->shouldReceive('get')->with('code')->andReturn($driver);

        $result = $this->engine->run($task);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid payload', $result->error);

        Event::assertDispatched(TaskFailed::class);
    }

    public function test_validate_returns_correct_status(): void
    {
        $task = Task::factory()->create([
            'driver' => 'code',
            'payload' => ['language' => 'php', 'source' => 'ok'],
        ]);

        $driver = Mockery::mock(\App\Contracts\ExecutionDriverInterface::class);
        $driver->shouldReceive('validatePayload')->andReturn(true);

        $this->registry->shouldReceive('get')->with('code')->andReturn($driver);

        $validation = $this->engine->validate($task);

        $this->assertTrue($validation['valid']);
        $this->assertSame('code', $validation['driver']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}