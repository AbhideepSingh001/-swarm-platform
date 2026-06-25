<?php

namespace App\Services;

use App\Events\Tasks\OrchestrationCompleted;
use App\Events\Tasks\TaskAssigned;
use App\Events\Tasks\TaskCreated;
use App\Events\Tasks\TaskProgressUpdated;
use App\Events\Tasks\TaskStatusChanged;
use App\Models\Agent;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskComment;
use App\Models\TaskDependency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskOrchestrationService
{
    public function __construct(
        private AgentLoadBalancer $loadBalancer,
    ) {}

    public function createTask(array $data, array $subtasks = []): Task
    {
        return DB::transaction(function () use ($data, $subtasks) {
            $task = Task::create(array_merge($data, [
                'status' => $data['status'] ?? 'pending',
            ]));

            foreach ($subtasks as $subtaskData) {
                $subtask = Task::create(array_merge($subtaskData, [
                    'parent_task_id' => $task->id,
                    'orchestration_id' => $task->orchestration_id,
                    'creator_id' => $task->creator_id ?? auth()->id(),
                    'status' => 'pending',
                ]));

                if ($subtaskData['requires_parent'] ?? true) {
                    TaskDependency::create([
                        'task_id' => $subtask->id,
                        'depends_on_task_id' => $task->id,
                        'type' => 'requires',
                    ]);
                }
            }

            broadcast(new TaskCreated($task));

            if ($task->status === 'pending' && ($data['auto_assign'] ?? false)) {
                $this->assignTask($task);
            }

            return $task->load('subtasks');
        });
    }

    public function assignTask(Task $task, ?Agent $agent = null): TaskAssignment
    {
        if (!$agent) {
            $agent = $this->loadBalancer->selectAgent($task);
        }

        if (!$agent) {
            throw new \RuntimeException('No suitable agent available for task assignment.');
        }

        $assignment = TaskAssignment::create([
            'task_id' => $task->id,
            'assignable_type' => Agent::class,
            'assignable_id' => $agent->id,
            'role' => 'primary',
            'assigned_at' => now(),
            'assignment_note' => "Auto-assigned by orchestrator for {$task->task_type} task",
        ]);

        $task->update(['status' => 'assigned']);

        broadcast(new TaskAssigned($task, $assignment));

        return $assignment;
    }

    public function acceptTask(Task $task, Agent $agent): void
    {
        $assignment = $task->assignments()
            ->where('assignable_type', Agent::class)
            ->where('assignable_id', $agent->id)
            ->whereNull('accepted_at')
            ->firstOrFail();

        $assignment->accept();

        $task->transitionTo('in_progress');
    }

    public function updateProgress(Task $task, int $percent, ?string $message = null): void
    {
        $oldProgress = $task->progress_percent;
        $task->update(['progress_percent' => min(100, max(0, $percent))]);

        broadcast(new TaskProgressUpdated($task, $oldProgress, $percent, $message));
    }

    public function completeTask(Task $task, array $result = []): void
    {
        $task->update(['result' => $result]);
        $task->transitionTo('completed');

        TaskComment::create([
            'task_id' => $task->id,
            'commentable_type' => Agent::class,
            'commentable_id' => $task->primaryAssignee()?->assignable_id ?? 1,
            'content' => 'Task completed successfully.',
            'type' => 'result',
            'metadata' => ['result_summary' => $result],
        ]);

        $this->unblockDependentTasks($task);
        $this->checkOrchestrationCompletion($task->orchestration_id);
    }

    public function failTask(Task $task, string $reason, array $metadata = []): void
    {
        $task->increment('retry_count');

        if ($task->retry_count < $task->max_retries) {
            $task->update([
                'status' => 'pending',
                'progress_percent' => 0,
                'metadata' => array_merge($task->metadata ?? [], [
                    'last_failure' => [
                        'reason' => $reason,
                        'at' => now()->toIso8601String(),
                        'retry_count' => $task->retry_count,
                    ],
                ]),
            ]);

            $this->assignTask($task);
        } else {
            $task->transitionTo('failed');

            TaskComment::create([
                'task_id' => $task->id,
                'commentable_type' => Agent::class,
                'commentable_id' => $task->primaryAssignee()?->assignable_id ?? 1,
                'content' => "Task failed after {$task->max_retries} retries: {$reason}",
                'type' => 'system',
                'metadata' => $metadata,
            ]);
        }
    }

    public function addDependency(Task $task, Task $dependsOn, string $type = 'requires'): TaskDependency
    {
        $dependency = TaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOn->id,
            'type' => $type,
        ]);

        if ($type === 'requires' && !$task->canStart()) {
            $task->update(['status' => 'pending']);
        }

        return $dependency;
    }

    public function addComment(Task $task, $commentable, string $content, string $type = 'note', array $metadata = []): TaskComment
    {
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'commentable_type' => get_class($commentable),
            'commentable_id' => $commentable->id,
            'content' => $content,
            'type' => $type,
            'metadata' => $metadata,
        ]);

        broadcast(new \App\Events\Tasks\TaskCommentAdded($task, $comment));

        return $comment;
    }

    public function createWorkflow(string $name, array $steps, int $creatorId, ?int $planId = null): Task
    {
        $orchestrationId = 'orch_' . Str::uuid();

        return DB::transaction(function () use ($name, $steps, $creatorId, $orchestrationId, $planId) {
            $rootTask = Task::create([
                'creator_id' => $creatorId,
                'plan_id' => $planId,
                'title' => $name,
                'description' => 'Workflow orchestration root',
                'task_type' => 'custom',
                'status' => 'pending',
                'orchestration_id' => $orchestrationId,
                'priority' => 'medium',
            ]);

            $createdTasks = [$rootTask->id => $rootTask];
            $stepMap = [];

            foreach ($steps as $index => $step) {
                $task = Task::create([
                    'creator_id' => $creatorId,
                    'plan_id' => $planId,
                    'title' => $step['title'],
                    'description' => $step['description'] ?? null,
                    'task_type' => $step['type'] ?? 'custom',
                    'priority' => $step['priority'] ?? 'medium',
                    'payload' => $step['payload'] ?? null,
                    'parent_task_id' => $rootTask->id,
                    'orchestration_id' => $orchestrationId,
                    'status' => 'pending',
                    'estimated_duration_minutes' => $step['estimated_duration'] ?? null,
                ]);

                $createdTasks[$task->id] = $task;
                $stepMap[$index] = $task->id;
            }

            foreach ($steps as $index => $step) {
                if (!empty($step['depends_on'])) {
                    foreach ((array) $step['depends_on'] as $depIndex) {
                        if (isset($stepMap[$depIndex])) {
                            TaskDependency::create([
                                'task_id' => $stepMap[$index],
                                'depends_on_task_id' => $stepMap[$depIndex],
                                'type' => 'requires',
                            ]);
                        }
                    }
                }
            }

            broadcast(new TaskCreated($rootTask));

            foreach ($createdTasks as $task) {
                if ($task->id !== $rootTask->id && $task->canStart()) {
                    $this->assignTask($task);
                }
            }

            return $rootTask->load('subtasks.dependencies');
        });
    }

    private function unblockDependentTasks(Task $completedTask): void
    {
        $blockedTasks = $completedTask->blocks()
            ->where('status', 'pending')
            ->get();

        foreach ($blockedTasks as $blockedTask) {
            if ($blockedTask->canStart()) {
                $this->assignTask($blockedTask);
            }
        }
    }

    private function checkOrchestrationCompletion(string $orchestrationId): void
    {
        $total = Task::where('orchestration_id', $orchestrationId)
            ->whereNotNull('parent_task_id')
            ->count();

        $completed = Task::where('orchestration_id', $orchestrationId)
            ->whereNotNull('parent_task_id')
            ->whereIn('status', ['completed', 'cancelled', 'failed'])
            ->count();

        if ($total > 0 && $total === $completed) {
            $failed = Task::where('orchestration_id', $orchestrationId)
                ->whereNotNull('parent_task_id')
                ->where('status', 'failed')
                ->count();

            broadcast(new OrchestrationCompleted($orchestrationId, [
                'total_tasks' => $total,
                'completed' => $completed - $failed,
                'failed' => $failed,
                'success_rate' => $total > 0 ? round((($completed - $failed) / $total) * 100, 2) : 0,
            ]));
        }
    }
}