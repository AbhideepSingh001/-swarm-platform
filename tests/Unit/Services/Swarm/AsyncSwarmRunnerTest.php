<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Swarm;

use App\Services\Swarm\AsyncSwarmRunner;
use Tests\TestCase;

class AsyncSwarmRunnerTest extends TestCase
{
    public function test_service_can_be_resolved(): void
    {
        $this->assertInstanceOf(AsyncSwarmRunner::class, app(AsyncSwarmRunner::class));
    }
}
