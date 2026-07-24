<?php

declare(strict_types=1);

namespace App\Services\Swarm;

use App\Models\TaskResult;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AgentDispatcher
{
    public function dispatch(array $step, array $context = [], bool $mockMode = false): array
    {
        $stepId = $step['id'] ?? 'unknown';
        $agentId = $step['agent_id'] ?? $step['agent'] ?? 'default';

        Log::info('AgentDispatcher: dispatching step', [
            'step_id' => $stepId,
            'agent_id' => $agentId,
            'mock_mode' => $mockMode,
        ]);

        if ($mockMode) {
            return $this->mockDispatch($step, $context);
        }

        return $this->callDay16System($step, $context);
    }

    protected function callDay16System(array $step, array $context): array
    {
        try {
            throw new RuntimeException(
                'Day 16 agent system not wired. ' .
                'Set mock_mode=true or implement callDay16System() in AgentDispatcher. ' .
                'See the WIRING POINT comment in the source.'
            );
        } catch (Throwable $e) {
            $stepId = $step['id'] ?? 'unknown';

            Log::error('AgentDispatcher: Day 16 call failed', [
                'step_id' => $stepId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            $message = $e->getMessage();
            throw new RuntimeException(
                "Agent dispatch failed for step [{$stepId}]: {$message}",
                0,
                $e
            );
        }
    }

    protected function mockDispatch(array $step, array $context): array
    {
        $stepId = $step['id'] ?? 'mock-step';
        $agentId = $step['agent_id'] ?? $step['agent'] ?? 'mock-agent';
        $stepName = $step['name'] ?? $stepId;

        usleep(random_int(1000, 5000));

        $forceFailure = $context['_force_mock_failure'] ?? false;
        if (random_int(1, 100) <= 10 && $forceFailure) {
            throw new RuntimeException("Mock failure for step [{$stepId}]");
        }

        $mockResult = [
            'step_id' => $stepId,
            'agent_id' => $agentId,
            'status' => 'completed',
            'output' => [
                'result' => "Mock execution of [{$stepName}]",
                'timestamp' => now()->toIso8601String(),
                'context_keys' => array_keys($context),
            ],
            'metadata' => [
                'mock' => true,
                'processing_time_ms' => random_int(10, 100),
            ],
        ];

        if (class_exists(TaskResult::class)) {
            try {
                TaskResult::create([
                    'task_id' => $stepId,
                    'agent_id' => $agentId,
                    'status' => 'completed',
                    'result' => $mockResult,
                    'completed_at' => now(),
                ]);
            } catch (Throwable $ex) {
                // Ignore
            }
        }

        Log::info('AgentDispatcher: mock result generated', [
            'step_id' => $stepId,
            'status' => 'completed',
        ]);

        return $mockResult;
    }

    public function isWired(): bool
    {
        return false;
    }
}
