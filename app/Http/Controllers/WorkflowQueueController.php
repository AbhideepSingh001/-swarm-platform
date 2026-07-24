<?php

namespace App\Http\Controllers;

use App\Models\WorkflowExecution;
use App\Services\Swarm\AsyncSwarmRunner;
use App\Services\Swarm\WorkflowStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowQueueController extends Controller
{
    public function __construct(
        private AsyncSwarmRunner $runner,
        private WorkflowStateMachine $stateMachine,
    ) {}

    public function status(Request $request, WorkflowExecution $execution): JsonResponse
    {
        $batchStatus = $this->runner->status($execution);

        return response()->json([
            'execution_id' => $execution->id,
            'workflow_id' => $execution->swarm_workflow_id,
            'status' => $execution->status,
            'batch_status' => $batchStatus,
            'results' => $execution->results,
            'checkpoint' => $execution->checkpoint,
            'started_at' => $execution->started_at?->toIso8601String(),
            'finished_at' => $execution->finished_at?->toIso8601String(),
        ]);
    }

    public function cancel(Request $request, WorkflowExecution $execution): JsonResponse
    {
        if ($execution->isTerminal()) {
            return response()->json([
                'message' => 'Workflow is already terminal',
                'status' => $execution->status,
            ], 422);
        }

        $cancelled = $this->runner->cancel($execution);

        return response()->json([
            'message' => $cancelled ? 'Workflow cancelled' : 'Failed to cancel workflow',
            'status' => $execution->fresh()->status,
        ]);
    }
}
