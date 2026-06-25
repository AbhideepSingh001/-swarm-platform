<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
    'plan_id', 'task_id', 'title', 'description', 'priority',
    'estimated_duration_minutes', 'agent_type', 'status', 'depends_on',
    'result', 'retry_count', 'last_error', 'started_at', 'completed_at',
    'failed_at', 'config', 'creator_id',
    'orchestration_id', 'parent_task_id', 'task_type', 'progress_percent',
    'max_retries', 'scheduled_at', 'deadline_at', 'actual_duration_minutes',
    'metadata',
    // Day 15
    'driver',
    'payload',
    'output',
    'attempts',
];

    protected $casts = [
    'depends_on' => 'array',
    'result' => 'array',
    'config' => 'array',
    'metadata' => 'array',
    'payload' => 'array',      // Add this
    'started_at' => 'datetime',
    'completed_at' => 'datetime',
    'failed_at' => 'datetime',
    'scheduled_at' => 'datetime',
    'deadline_at' => 'datetime',
];
    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            if (empty($task->orchestration_id)) {
                $task->orchestration_id = 'orch_' . uniqid();
            }
            if (empty($task->task_id)) {
                $task->task_id = 'task_' . uniqid();
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function primaryAssignee(): ?TaskAssignment
    {
        return $this->assignments->where('role', 'primary')->first();
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    public function blockedBy(): BelongsToMany
    {
        return $this->belongsToMany(
            Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id'
        )->withPivot('type');
    }

    public function blocks(): BelongsToMany
    {
        return $this->belongsToMany(
            Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id'
        )->withPivot('type');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(TaskExecution::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['assigned', 'in_progress', 'review']);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('deadline_at')
            ->where('deadline_at', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled', 'failed']);
    }

    public function scopeReadyToStart($query)
    {
        return $query->where('status', 'pending')
            ->whereDoesntHave('blockedBy', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled']);
            });
    }

    public function isOverdue(): bool
    {
        return $this->deadline_at && $this->deadline_at->isPast()
            && !in_array($this->status, ['completed', 'cancelled', 'failed']);
    }

    public function canStart(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }
        return $this->blockedBy()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->doesntExist();
    }

    public function transitionTo(string $status, array $metadata = []): void
    {
        $oldStatus = $this->status;
        $this->status = $status;

        if ($status === 'in_progress' && !$this->started_at) {
            $this->started_at = now();
        }

        if (in_array($status, ['completed', 'cancelled', 'failed'])) {
            $this->completed_at = now();
            if ($this->started_at) {
                $this->actual_duration_minutes = $this->started_at->diffInMinutes(now());
            }
        }

        if (!empty($metadata)) {
            $this->metadata = array_merge($this->metadata ?? [], $metadata);
        }

        $this->save();

        broadcast(new \App\Events\Tasks\TaskStatusChanged($this, $oldStatus, $status));
    }
}