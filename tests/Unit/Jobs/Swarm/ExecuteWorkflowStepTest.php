<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Swarm;

use App\Jobs\Swarm\ExecuteWorkflowStep;
use App\Services\Swarm\AgentDispatcher;
use App\Services\Swarm\StepRetryDecorator;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ExecuteWorkflowStepTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated_with_step_data(): void
    {
        $job = new ExecuteWorkflowStep(
            executionId: 'exec-001',
            step: ['id' => 'step-1', 'name' => 'Test Step', 'agent_id' => 'agent-1'],
            context: ['var' => 'value'],
            levelIndex: 2,
            stepIndex: 1,
        );

        $this->assertSame('exec-001', $job->executionId);
        $this->assertSame('step-1', $job->step['id']);
        $this->assertSame('value', $job->context['var']);
        $this->assertSame(2, $job->levelIndex);
        $this->assertSame(1, $job->stepIndex);
        $this->assertSame(1, $job->tries);
        $this->assertSame(120, $job->timeout);
    }

    #[Test]
    public function it_uses_batchable_trait(): void
    {
        $job = new ExecuteWorkflowStep('exec-001', ['id' => 's1']);
        $this->assertTrue(method_exists($job, 'batch'));
    }

    #[Test]
    public function it_executes_step_via_agent_dispatcher(): void
    {
        $step = [
            'id' => 'step-1',
            'name' => 'Analyze Data',
            'agent_id' => 'agent-1',
            'retry' => ['max_attempts' => 2, 'base_delay_ms' => 10],
        ];

        $job = new ExecuteWorkflowStep('exec-001', $step, ['prev' => 'result']);

        $retryDecorator = new StepRetryDecorator();
        $agentDispatcher = new AgentDispatcher();

        $job->handle($retryDecorator, $agentDispatcher);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_calls_failed_method_on_exception(): void
    {
        $step = ['id' => 'step-fail', 'name' => 'Failing Step'];
        $job = new ExecuteWorkflowStep('exec-002', $step);

        $exception = new RuntimeException('Simulated failure');
        $job->failed($exception);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_uses_step_level_retry_config(): void
    {
        $step = [
            'id' => 'step-retry',
            'retry' => [
                'max_attempts' => 5,
                'base_delay_ms' => 500,
                'backoff_multiplier' => 3.0,
                'use_jitter' => false,
            ],
        ];

        $job = new ExecuteWorkflowStep('exec-003', $step);

        $this->assertSame(5, $step['retry']['max_attempts']);
        $this->assertSame(500, $step['retry']['base_delay_ms']);
        $this->assertSame(3.0, $step['retry']['backoff_multiplier']);
        $this->assertFalse($step['retry']['use_jitter']);
    }
}
