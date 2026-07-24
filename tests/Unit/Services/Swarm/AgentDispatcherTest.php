<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Swarm;

use App\Services\Swarm\AgentDispatcher;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class AgentDispatcherTest extends TestCase
{
    private AgentDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new AgentDispatcher();
    }

    #[Test]
    public function it_dispatches_in_mock_mode_and_returns_result(): void
    {
        $step = [
            'id' => 'step-001',
            'name' => 'Test Step',
            'agent_id' => 'agent-alpha',
            'input' => ['query' => 'hello'],
        ];

        $result = $this->dispatcher->dispatch($step, ['workflow_id' => 'wf-1'], mockMode: true);

        $this->assertSame('step-001', $result['step_id']);
        $this->assertSame('agent-alpha', $result['agent_id']);
        $this->assertSame('completed', $result['status']);
        $this->assertArrayHasKey('output', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertTrue($result['metadata']['mock']);
    }

    #[Test]
    public function it_passes_context_to_mock_result(): void
    {
        $step = ['id' => 'step-002', 'name' => 'Context Test'];
        $context = ['foo' => 'bar', 'baz' => 123];

        $result = $this->dispatcher->dispatch($step, $context, mockMode: true);

        $this->assertContains('foo', $result['output']['context_keys']);
        $this->assertContains('baz', $result['output']['context_keys']);
    }

    #[Test]
    public function it_throws_when_day16_not_wired_and_mock_disabled(): void
    {
        $step = ['id' => 'step-003', 'name' => 'Real Call'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Day 16 agent system not wired');

        $this->dispatcher->dispatch($step, [], mockMode: false);
    }

    #[Test]
    public function it_reports_not_wired_by_default(): void
    {
        $this->assertFalse($this->dispatcher->isWired());
    }

    #[Test]
    public function it_uses_default_agent_when_not_specified(): void
    {
        $step = ['id' => 'step-004', 'name' => 'No Agent'];

        $result = $this->dispatcher->dispatch($step, [], mockMode: true);

        $this->assertSame('mock-agent', $result['agent_id']);
    }

    #[Test]
    public function it_includes_timestamp_in_mock_output(): void
    {
        $step = ['id' => 'step-005', 'name' => 'Timestamp Check'];

        $result = $this->dispatcher->dispatch($step, [], mockMode: true);

        $this->assertArrayHasKey('timestamp', $result['output']);
        $this->assertNotEmpty($result['output']['timestamp']);
    }
}
