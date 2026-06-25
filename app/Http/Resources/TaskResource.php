<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'orchestration_id' => $this->orchestration_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'task_type' => $this->task_type,
            'agent_type' => $this->agent_type,
            'payload' => $this->payload,
            'result' => $this->result,
            'config' => $this->config,
            'metadata' => $this->metadata,
            'progress_percent' => $this->progress_percent,
            'retry_count' => $this->retry_count,
            'max_retries' => $this->max_retries,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'deadline_at' => $this->deadline_at?->toIso8601String(),
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'actual_duration_minutes' => $this->actual_duration_minutes,
            'is_overdue' => $this->isOverdue(),
            'can_start' => $this->canStart(),
            'plan' => $this->whenLoaded('plan'),
            'parent' => new TaskResource($this->whenLoaded('parent')),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'assignments' => $this->whenLoaded('assignments', function () {
                return $this->assignments->map(fn ($a) => [
                    'id' => $a->id,
                    'role' => $a->role,
                    'assigned_at' => $a->assigned_at?->toIso8601String(),
                    'accepted_at' => $a->accepted_at?->toIso8601String(),
                    'assignable' => $a->assignable,
                ]);
            }),
            'dependencies' => $this->whenLoaded('dependencies', function () {
                return $this->dependencies->map(fn ($d) => [
                    'id' => $d->id,
                    'type' => $d->type,
                    'depends_on' => new TaskResource($d->dependsOn),
                ]);
            }),
            'comments' => $this->whenLoaded('comments'),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}