<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Swarm;

use App\Services\Swarm\StepRetryDecorator;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class StepRetryDecoratorTest extends TestCase
{
    private StepRetryDecorator $decorator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->decorator = new StepRetryDecorator();
    }

    #[Test]
    public function it_executes_successfully_on_first_attempt(): void
    {
        $result = $this->decorator->call(fn () => 'success');
        $this->assertSame('success', $result);
    }

    #[Test]
    public function it_retries_on_failure_and_succeeds_on_second_attempt(): void
    {
        $attempts = 0;

        $result = $this->decorator->call(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 2) {
                throw new RuntimeException('Temporary failure');
            }
            return 'recovered';
        }, maxAttempts: 3, baseDelayMs: 10);

        $this->assertSame('recovered', $result);
        $this->assertSame(2, $attempts);
    }

    #[Test]
    public function it_exhausts_all_retries_and_throws_last_exception(): void
    {
        $attempts = 0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Persistent failure');

        $this->decorator->call(function () use (&$attempts) {
            $attempts++;
            throw new RuntimeException('Persistent failure');
        }, maxAttempts: 3, baseDelayMs: 10);
    }

    #[Test]
    public function it_skips_retry_for_non_retryable_exceptions(): void
    {
        $attempts = 0;

        $this->expectException(InvalidArgumentException::class);

        $this->decorator->call(function () use (&$attempts) {
            $attempts++;
            throw new InvalidArgumentException('Bad input');
        }, maxAttempts: 3, baseDelayMs: 10, retryOnly: [RuntimeException::class]);

        $this->assertSame(1, $attempts);
    }

    #[Test]
    public function it_uses_exponential_backoff_with_jitter(): void
    {
        $attempts = 0;

        $this->decorator->call(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 4) {
                throw new RuntimeException('Fail');
            }
            return 'done';
        }, maxAttempts: 4, baseDelayMs: 50, backoffMultiplier: 2.0, useJitter: true);

        $this->assertSame(4, $attempts);
    }
}
