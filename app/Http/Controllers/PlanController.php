<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlannerAgent;
use App\Services\PlanExecutor;
use App\Services\GeminiService;
use App\Jobs\DecomposeGoalJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    private PlannerAgent $planner;
    private PlanExecutor $executor;
    private GeminiService $gemini;

    public function __construct(PlannerAgent $planner, PlanExecutor $executor, GeminiService $gemini)
    {
        $this->planner = $planner;
        $this->executor = $executor;
        $this->gemini = $gemini;
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'goal' => 'required|string|min:10|max:5000',
            'context' => 'nullable|array',
            'execute_immediately' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $goal = $request->input('goal');
        $context = $request->input('context', []);
        $executeImmediately = $request->input('execute_immediately', false);

        try {
            if (strlen($goal) > 500 || count($context) > 5) {
                $job = DecomposeGoalJob::dispatch($goal, $context, $request->user()?->id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Goal submitted for processing. Plan will be created asynchronously.',
                    'job_id' => $job->getJobId(),
                ], 202);
            }

            $plan = $this->planner->createPlan($goal, $context, $request->user()?->id);

            if ($executeImmediately) {
                $this->executor->execute($plan->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully',
                'data' => [
                    'plan' => $plan->load('tasks'),
                    'execution_order' => $this->planner->getExecutionOrder($plan->id),
                ],
            ], 201);

        } catch (\App\Exceptions\LLMException $e) {
            Log::error('LLM error creating plan', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate plan: ' . $e->getMessage(),
                'error_code' => 'LLM_ERROR',
            ], 503);
        } catch (\Exception $e) {
            Log::error('Unexpected error creating plan', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $plan = Plan::with('tasks')->find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'plan' => $plan,
                'execution_order' => $plan->executionOrder(),
                'ready_tasks' => $this->planner->getReadyTasks($plan->id),
            ],
        ]);
    }

    public function status(int $id): JsonResponse
    {
        try {
            $status = $this->executor->getStatus($id);
            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function execute(int $id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found',
            ], 404);
        }

        if (!$plan->canStart()) {
            return response()->json([
                'success' => false,
                'message' => "Plan cannot be started (current status: {$plan->status})",
            ], 409);
        }

        try {
            $this->executor->execute($plan->id);

            return response()->json([
                'success' => true,
                'message' => 'Plan execution started',
                'data' => [
                    'plan_id' => $plan->id,
                    'status' => 'running',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function pause(int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $this->executor->pause($plan->id);

        return response()->json([
            'success' => true,
            'message' => 'Plan paused',
            'data' => ['status' => 'paused'],
        ]);
    }

    public function resume(int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $this->executor->resume($plan->id);

        return response()->json([
            'success' => true,
            'message' => 'Plan resumed',
            'data' => ['status' => 'running'],
        ]);
    }

    public function cancel(int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $this->executor->cancel($plan->id);

        return response()->json([
            'success' => true,
            'message' => 'Plan cancelled',
            'data' => ['status' => 'cancelled'],
        ]);
    }

    public function regenerate(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'feedback' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $plan = $this->planner->regeneratePlan($id, $request->input('feedback', []));

            return response()->json([
                'success' => true,
                'message' => 'Plan regenerated successfully',
                'data' => [
                    'plan' => $plan->load('tasks'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = Plan::with('tasks');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $plans = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    public function keyStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'key_status' => $this->gemini->getKeyStatus(),
                'total_keys' => count(config('agents.llm.gemini.api_keys')),
            ],
        ]);
    }
}