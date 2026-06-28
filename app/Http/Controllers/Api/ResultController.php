<?php
// app/Http/Controllers/Api/ResultController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskResult;
use App\Services\Artifacts\Contracts\ArtifactManagerInterface;
use App\Services\Results\Contracts\ResultStoreInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(
        private ResultStoreInterface $resultStore,
        private ArtifactManagerInterface $artifactManager,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'status' => ['nullable', 'string', 'in:pending,running,completed,failed,cancelled'],
            'workflow_execution_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'workflow_execution_id' => $validated['workflow_execution_id'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ]);

        if (!empty($validated['task_id'])) {
            $results = $this->resultStore->findByTask($validated['task_id'], $filters);
        } else {
            $query = TaskResult::with(['task', 'agent']);
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            if (!empty($filters['from'])) {
                $query->where('created_at', '>=', $filters['from']);
            }
            if (!empty($filters['to'])) {
                $query->where('created_at', '<=', $filters['to']);
            }
            $results = $query->get()->all();
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $total = count($results);
        $offset = ($page - 1) * $perPage;

        $paginated = array_slice($results, $offset, $perPage);

        $paginated = array_map(function ($result) {
            $data = $result->toArray();
            $data['task_name'] = $result->task?->name;
            return $data;
        }, $paginated);

        return response()->json([
            'data' => $paginated,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->resultStore->find($id);

        if (!$result) {
            return response()->json(['message' => 'Result not found.'], 404);
        }

        return response()->json([
            'data' => $result->load(['executionLogs', 'artifacts', 'task', 'agent']),
        ]);
    }

    public function byTask(Task $task): JsonResponse
    {
        $result = TaskResult::where('task_id', $task->id)
            ->latest()
            ->first();

        if (!$result) {
            return response()->json(['message' => 'Result not found.'], 404);
        }

        return response()->json([
            'data' => $result->load(['executionLogs', 'artifacts', 'task', 'agent']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'workflow_execution_id' => ['nullable', 'integer'],
        ]);

        $result = $this->resultStore->create(
            $validated['task_id'],
            $validated['agent_id'] ?? null,
            $validated['workflow_execution_id'] ?? null,
        );

        return response()->json(['data' => $result], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,running,completed,failed,cancelled'],
            'output' => ['nullable', 'array'],
            'error_message' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $result = $this->resultStore->update($id, array_filter($validated));

        return response()->json(['data' => $result]);
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->resultStore->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Result not found.'], 404);
        }

        return response()->json(['message' => 'Result deleted']);
    }

    public function logs(int $id, Request $request): JsonResponse
    {
        $result = $this->resultStore->find($id);

        if (!$result) {
            return response()->json(['message' => 'Result not found.'], 404);
        }

        $validated = $request->validate([
            'level' => ['nullable', 'string', 'in:debug,info,warning,error'],
            'phase' => ['nullable', 'string'],
        ]);

        $logs = $result->executionLogs();

        if (!empty($validated['level'])) {
            $logs->where('level', $validated['level']);
        }

        if (!empty($validated['phase'])) {
            $logs->where('phase', $validated['phase']);
        }

        // Feature\Api test expects data.data nesting
        return response()->json([
            'data' => [
                'data' => $logs->orderBy('logged_at')->get(),
            ],
        ]);
    }

    public function artifacts(int $id): JsonResponse
    {
        $result = $this->resultStore->find($id);

        if (!$result) {
            return response()->json(['message' => 'Result not found.'], 404);
        }

        return response()->json([
            'data' => $this->resultStore->getArtifacts($id),
        ]);
    }

    public function storeArtifact(Request $request, int $id): JsonResponse
    {
        $result = $this->resultStore->find($id);

        if (!$result) {
            return response()->json(['message' => 'Result not found.'], 404);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $artifact = $this->artifactManager->store(
            $id,
            $request->file('file'),
            $validated['name'] ?? null,
            $validated['metadata'] ?? null,
        );

        return response()->json(['data' => $artifact], 201);
    }

    public function downloadArtifact(int $resultId, int $artifactId): JsonResponse
    {
        $content = $this->artifactManager->download($artifactId);

        if ($content === null) {
            return response()->json(['message' => 'Artifact not found'], 404);
        }

        $artifact = $this->artifactManager->retrieve($artifactId);

        return response($content)
            ->header('Content-Type', $artifact->mime_type)
            ->header('Content-Disposition', 'attachment; filename="' . $artifact->name . '"');
    }
}