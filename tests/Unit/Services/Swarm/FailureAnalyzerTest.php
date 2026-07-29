<?php

namespace Tests\Unit\Services\Swarm;

use App\Services\Swarm\FailureAnalyzer;
use Tests\TestCase;

class FailureAnalyzerTest extends TestCase
{
    protected FailureAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new FailureAnalyzer();
    }

    /** @test */
    public function it_categorizes_timeout_errors(): void
    {
        $this->assertEquals(
            FailureAnalyzer::TIMEOUT,
            $this->analyzer->categorize(new \Exception('Request timed out after 30s'))
        );

        $this->assertEquals(
            FailureAnalyzer::TIMEOUT,
            $this->analyzer->categorize(new \Exception('Curl timeout'))
        );
    }

    /** @test */
    public function it_categorizes_syntax_errors(): void
    {
        $this->assertEquals(
            FailureAnalyzer::SYNTAX_ERROR,
            $this->analyzer->categorize(new \Exception('Parse error: invalid JSON'))
        );

        $this->assertEquals(
            FailureAnalyzer::SYNTAX_ERROR,
            $this->analyzer->categorize(new \JsonException('Malformed JSON'))
        );
    }

    /** @test */
    public function it_categorizes_agent_errors(): void
    {
        $this->assertEquals(
            FailureAnalyzer::AGENT_ERROR,
            $this->analyzer->categorize(new \Exception('Rate limit exceeded for API key'))
        );

        $this->assertEquals(
            FailureAnalyzer::AGENT_ERROR,
            $this->analyzer->categorize(new \Exception('Model not found: gpt-99'))
        );
    }

    /** @test */
    public function it_defaults_to_unknown_for_unrecognized_errors(): void
    {
        $this->assertEquals(
            FailureAnalyzer::UNKNOWN,
            $this->analyzer->categorize(new \Exception('Some random weird thing'))
        );
    }

    /** @test */
    public function it_returns_unknown_for_null_exception(): void
    {
        $this->assertEquals(FailureAnalyzer::UNKNOWN, $this->analyzer->categorize(null));
    }
}
