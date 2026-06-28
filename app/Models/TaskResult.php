<?php
// app/Models/TaskResult.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'agent_id',
        'workflow_execution_id',
        'status',
        'output',
        'error_message',
        'started_at',
        'completed_at',
        'duration_ms',
        'metadata',
    ];

    protected $casts = [
        'output' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    // REMOVED: workflowExecution() relation — model doesn't exist

    public function executionLogs(): HasMany
    {
        return $this->hasMany(TaskExecutionLog::class)->orderBy('logged_at');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(array $output, ?array $metadata = null): void
    {
        $startedAt = $this->started_at ?? now();
        $this->update([
            'status' => 'completed',
            'output' => $output,
            'completed_at' => now(),
            'duration_ms' => (int) (now()->diffInMilliseconds($startedAt)),
            'metadata' => $metadata,
        ]);
    }

    public function markAsFailed(string $errorMessage, ?array $metadata = null): void
    {
        $startedAt = $this->started_at ?? now();
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
            'duration_ms' => (int) (now()->diffInMilliseconds($startedAt)),
            'metadata' => $metadata,
        ]);
    }
}