<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeadLetter;
use App\Services\Swarm\CircuitBreaker;
use App\Services\Swarm\DeadLetterQueue;
use App\Services\Swarm\RecoveryOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeadLetterController extends Controller
{
    public function __construct(
        protected DeadLetterQueue $deadLetterQueue,
        protected RecoveryOrchestrator $recoveryOrchestrator,
        protected CircuitBreaker $circuitBreaker,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DeadLetter::class);

        $filters = $request->only(['status', 'failure_category', 'agent_id', 'execution_id', 'per_page']);

        $deadLetters = $this->deadLetterQueue->listOpen($filters);

        return response()->json([
            'data' => $deadLetters->items(),
            'meta' => [
                'current_page' => $deadLetters->currentPage(),
                'last_page' => $deadLetters->lastPage(),
                'per_page' => $deadLetters->perPage(),
                'total' => $deadLetters->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $deadLetter = $this->deadLetterQueue->retrieve($id);

        if (! $deadLetter) {
            return response()->json(['message' => 'Dead letter not found.'], 404);
        }

        Gate::authorize('view', $deadLetter);

        return response()->json(['data' => $deadLetter]);
    }

    public function retry(Request $request, int $id): JsonResponse
    {
        $deadLetter = DeadLetter::findOrFail($id);
        Gate::authorize('retry', $deadLetter);

        $result = $this->recoveryOrchestrator->retry($deadLetter, [
            'agent_id' => $request->input('agent_id'),
            'step_config' => $request->input('step_config', []),
            'context' => $request->input('context', []),
        ]);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function skip(Request $request, int $id): JsonResponse
    {
        $deadLetter = DeadLetter::findOrFail($id);
        Gate::authorize('skip', $deadLetter);

        $result = $this->recoveryOrchestrator->skip($deadLetter, $request->input('reason'));

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function reassign(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|string',
        ]);

        $deadLetter = DeadLetter::findOrFail($id);
        Gate::authorize('reassign', $deadLetter);

        $result = $this->recoveryOrchestrator->reassign(
            $deadLetter,
            $request->input('agent_id'),
            [
                'step_config' => $request->input('step_config', []),
                'context' => $request->input('context', []),
            ]
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function dismiss(int $id): JsonResponse
    {
        $deadLetter = DeadLetter::findOrFail($id);
        Gate::authorize('dismiss', $deadLetter);

        $deadLetter->markDismissed(request('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Dead letter dismissed.',
        ]);
    }

    public function circuitStatus(Request $request, string $agentId): JsonResponse
    {
        return response()->json($this->circuitBreaker->status($agentId));
    }

    public function resetCircuit(Request $request, string $agentId): JsonResponse
    {
        $this->circuitBreaker->reset($agentId);

        return response()->json([
            'success' => true,
            'message' => "Circuit breaker reset for agent {$agentId}.",
        ]);
    }
}
