<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use App\Execution\RetryManager;
use App\Models\Task;
use App\ValueObjects\ExecutionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RetryManagerTest extends TestCase
{
    use RefreshDatabase;

    private RetryManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new RetryManager([
            'base_delay' => 0, // No delay for fast tests
            'backoff_multiplier' => 2.0,
            'max_delay' => 10,
        ]);
        Event::fake();
    }

    public function test_returns_success_immediately(): void
    {
        $task = Task::factory()->create();

        $result = $this->manager->executeWithRetry(
            task: $task,
            callback: fn() => new ExecutionResult(success: true, output: 'ok'),
            maxAttempts: 3
        );

        $this->assertTrue($result->success);
        $this->assertSame('ok', $result->output);
    }

    public function test_retries_on_failure_then_succeeds(): void
    {
        $task = Task::factory()->create();
        $attempts = 0;

        $result = $this->manager->executeWithRetry(
            task: $task,
            callback: function () use (&$attempts) {
                $attempts++;
                return $attempts < 3
                    ? new ExecutionResult(success: false, error: 'timeout')
                    : new ExecutionResult(success: true, output: 'finally');
            },
            maxAttempts: 3,
            retryableErrors: ['timeout']
        );

        $this->assertTrue($result->success);
        $this->assertSame('finally', $result->output);
        $this->assertSame(3, $attempts);
    }

    public function test_does_not_retry_non_retryable_errors(): void
    {
        $task = Task::factory()->create();
        $attempts = 0;

        $result = $this->manager->executeWithRetry(
            task: $task,
            callback: function () use (&$attempts) {
                $attempts++;
                return new ExecutionResult(success: false, error: 'syntax error');
            },
            maxAttempts: 3,
            retryableErrors: ['timeout'] // syntax error not in list
        );

        $this->assertFalse($result->success);
        $this->assertSame(1, $attempts); // Only tried once
    }

    public function test_exhausts_all_attempts(): void
    {
        $task = Task::factory()->create();

        $result = $this->manager->executeWithRetry(
            task: $task,
            callback: fn() => new ExecutionResult(success: false, error: 'timeout'),
            maxAttempts: 3,
            retryableErrors: ['timeout']
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Failed after 3 attempts', $result->error);
    }

    public function test_exponential_backoff_delays(): void
    {
        $manager = new RetryManager([
            'base_delay' => 2,
            'backoff_multiplier' => 2.0,
            'max_delay' => 100,
        ]);

        // Use reflection to test private method
        $method = new \ReflectionMethod($manager, 'calculateDelay');
        $method->setAccessible(true);

        $this->assertSame(2, $method->invoke($manager, 1)); // 2 * 2^0 = 2
        $this->assertSame(4, $method->invoke($manager, 2)); // 2 * 2^1 = 4
        $this->assertSame(8, $method->invoke($manager, 3)); // 2 * 2^2 = 8
    }
}