<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SwarmWorkflow;
use App\Models\WorkflowExecution;
use App\Services\Swarm\SwarmRunner;
use App\Services\Swarm\WorkflowStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WorkflowController extends Controller
{
    public function __construct(
        private SwarmRunner $runner,
        private WorkflowStateMachine $stateMachine,
    ) {}

    public function index(): JsonResponse
    {
        $workflows = SwarmWorkflow::withCount('executions')->get();

        return response()->json($workflows);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:swarm_workflows,name',
            'description' => 'nullable|string',
            'steps' => 'required|array|min:1',
            'steps.*.name' => 'required|string',
            'steps.*.agent' => 'required|string',
            'steps.*.task' => 'required|string',
            'steps.*.depends_on' => 'nullable|array',
            'steps.*.depends_on.*' => 'string',
            'steps.*.config' => 'nullable|array',
            'steps.*.max_retries' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $workflow = SwarmWorkflow::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        foreach ($request->input('steps') as $index => $stepData) {
            $workflow->steps()->create([
                'name' => $stepData['name'],
                'agent' => $stepData['agent'],
                'task' => $stepData['task'],
                'depends_on' => $stepData['depends_on'] ?? [],
                'config' => $stepData['config'] ?? [],
                'max_retries' => $stepData['max_retries'] ?? 0,
                'order' => $index,
            ]);
        }

        return response()->json($workflow->load('steps'), 201);
    }

    public function show(int $id): JsonResponse
    {
        $workflow = SwarmWorkflow::with('steps')->findOrFail($id);

        return response()->json($workflow);
    }

    public function execute(Request $request, int $id): JsonResponse
    {
        $workflow = SwarmWorkflow::with('steps')->findOrFail($id);

        if (!$workflow->is_active) {
            return response()->json(['error' => 'Workflow is inactive'], 403);
        }

        $context = $request->input('context', []);
        $execution = $this->runner->execute($workflow, $context);

        return response()->json([
            'execution_id' => $execution->id,
            'status' => $execution->status,
            'message' => 'Workflow execution started',
        ], 202);
    }

    public function executionStatus(int $executionId): JsonResponse
    {
        $execution = WorkflowExecution::with('workflow.steps')->findOrFail($executionId);

        return response()->json($this->runner->getStatus($execution));
    }

    public function pause(int $executionId): JsonResponse
    {
        $execution = WorkflowExecution::findOrFail($executionId);

        if (!$execution->isRunning()) {
            return response()->json(['error' => 'Execution is not running'], 409);
        }

        $this->runner->pause($execution);

        return response()->json([
            'execution_id' => $execution->id,
            'status' => $execution->fresh()->status,
            'message' => 'Execution paused',
        ]);
    }

    public function resume(int $executionId): JsonResponse
    {
        $execution = WorkflowExecution::with('workflow.steps')->findOrFail($executionId);

        if (!$execution->isPaused()) {
            return response()->json(['error' => 'Execution is not paused'], 409);
        }

        $this->runner->resume($execution);

        return response()->json([
            'execution_id' => $execution->id,
            'status' => $execution->fresh()->status,
            'message' => 'Execution resumed',
        ]);
    }

    public function cancel(int $executionId): JsonResponse
    {
        $execution = WorkflowExecution::findOrFail($executionId);

        if ($execution->isTerminal()) {
            return response()->json(['error' => 'Execution is already terminal'], 409);
        }

        $this->runner->cancel($execution);

        return response()->json([
            'execution_id' => $execution->id,
            'status' => $execution->fresh()->status,
            'message' => 'Execution cancelled',
        ]);
    }

    public function results(int $executionId): JsonResponse
    {
        $execution = WorkflowExecution::with('workflow.steps')->findOrFail($executionId);

        return response()->json([
            'execution_id' => $execution->id,
            'status' => $execution->status,
            'results' => $execution->results,
            'completed_steps' => $execution->getCompletedSteps(),
            'total_steps' => $execution->workflow->steps->count(),
        ]);
    }
}