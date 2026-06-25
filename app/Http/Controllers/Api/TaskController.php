<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\CreateTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Agent;
use App\Models\Task;
use App\Services\TaskOrchestrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TaskController extends Controller
{
    public function __construct(
        private TaskOrchestrationService $orchestrator,
    ) {}

    public function index(Request $request): ResourceCollection
    {
        $query = Task::with(['plan', 'creator', 'assignments.assignable', 'subtasks', 'dependencies.dependsOn']);

        if ($request->has('status')) {
            $query->whereIn('status', (array) $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('task_type')) {
            $query->where('task_type', $request->task_type);
        }

        if ($request->has('orchestration_id')) {
            $query->where('orchestration_id', $request->orchestration_id);
        }

        if ($request->has('agent_id')) {
            $query->whereHas('assignments', function ($q) use ($request) {
                $q->where('assignable_type', Agent::class)
                    ->where('assignable_id', $request->agent_id);
            });
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        if ($request->boolean('ready')) {
            $query->readyToStart();
        }

        $tasks = $query->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('scheduled_at', 'asc')
            ->paginate($request->get('per_page', 20));

        return TaskResource::collection($tasks);
    }

    public function store(CreateTaskRequest $request): JsonResponse
    {
        $task = $this->orchestrator->createTask(
            array_merge($request->validated(), ['creator_id' => auth()->id()]),
            $request->input('subtasks', [])
        );

        return response()->json([
            'message' => 'Task created successfully',
            'task' => new TaskResource($task),
        ], 201);
    }

    public function show(Task $task): JsonResponse
    {
        return response()->json([
            'task' => new TaskResource($task->load([
                'plan', 'creator', 'assignments.assignable', 'subtasks',
                'dependencies.dependsOn', 'blocks', 'comments.commentable', 'parent',
            ])),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $oldStatus = $task->status;
        $task->update($request->validated());

        if ($oldStatus !== $task->status) {
            broadcast(new \App\Events\Tasks\TaskStatusChanged($task, $oldStatus, $task->status));
        }

        return response()->json([
            'message' => 'Task updated successfully',
            'task' => new TaskResource($task->fresh()),
        ]);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function assign(Task $task, Request $request): JsonResponse
    {
        $agent = $request->has('agent_id')
            ? Agent::findOrFail($request->agent_id)
            : null;

        $assignment = $this->orchestrator->assignTask($task, $agent);

        return response()->json([
            'message' => 'Task assigned successfully',
            'assignment' => $assignment->load('assignable'),
        ]);
    }

    public function accept(Task $task, Request $request): JsonResponse
    {
        $agent = Agent::findOrFail($request->agent_id);
        $this->orchestrator->acceptTask($task, $agent);

        return response()->json(['message' => 'Task accepted']);
    }

    public function progress(Task $task, Request $request): JsonResponse
    {
        $request->validate([
            'percent' => 'required|integer|min:0|max:100',
            'message' => 'nullable|string',
        ]);

        $this->orchestrator->updateProgress($task, $request->percent, $request->message);

        return response()->json(['message' => 'Progress updated']);
    }

    public function complete(Task $task, Request $request): JsonResponse
    {
        $this->orchestrator->completeTask($task, $request->input('result', []));

        return response()->json(['message' => 'Task completed']);
    }

    public function fail(Task $task, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string',
            'metadata' => 'nullable|array',
        ]);

        $this->orchestrator->failTask($task, $request->reason, $request->input('metadata', []));

        return response()->json(['message' => 'Task marked as failed']);
    }

    public function addDependency(Task $task, Request $request): JsonResponse
    {
        $request->validate([
            'depends_on_task_id' => 'required|exists:tasks,id',
            'type' => 'in:blocks,requires,triggers',
        ]);

        $dependsOn = Task::findOrFail($request->depends_on_task_id);
        $dependency = $this->orchestrator->addDependency($task, $dependsOn, $request->type ?? 'requires');

        return response()->json([
            'message' => 'Dependency added',
            'dependency' => $dependency->load('dependsOn'),
        ]);
    }

    public function addComment(Task $task, Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
            'type' => 'in:note,status_update,blocker,result',
        ]);

        $comment = $this->orchestrator->addComment(
            $task,
            auth()->user(),
            $request->content,
            $request->type ?? 'note'
        );

        return response()->json([
            'message' => 'Comment added',
            'comment' => $comment->load('commentable'),
        ]);
    }

    public function createWorkflow(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'steps' => 'required|array|min:1',
            'steps.*.title' => 'required|string',
            'steps.*.type' => 'required|string',
            'steps.*.depends_on' => 'nullable|array',
        ]);

        $rootTask = $this->orchestrator->createWorkflow(
            $request->name,
            $request->steps,
            auth()->id()
        );

        return response()->json([
            'message' => 'Workflow created',
            'task' => new TaskResource($rootTask),
        ], 201);
    }

    public function stats(Request $request): JsonResponse
    {
        $query = Task::query();

        if ($request->has('orchestration_id')) {
            $query->where('orchestration_id', $request->orchestration_id);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'by_status' => (clone $query)->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_priority' => (clone $query)->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'by_type' => (clone $query)->selectRaw('task_type, COUNT(*) as count')
                ->groupBy('task_type')
                ->pluck('count', 'task_type'),
            'overdue' => (clone $query)->overdue()->count(),
            'active' => (clone $query)->active()->count(),
            'completed_today' => (clone $query)->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];

        return response()->json($stats);
    }
}