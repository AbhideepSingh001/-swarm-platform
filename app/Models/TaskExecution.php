<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'status',
        'output',
        'error',
        'attempt',
        'max_attempts',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isRetryable(): bool
    {
        return $this->attempt < $this->max_attempts && $this->status === 'failed';
    }

    public function markRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
            'attempt' => $this->attempt + 1,
        ]);
    }

    public function markCompleted(string $output, array $metadata = []): void
    {
        $this->update([
            'status' => 'completed',
            'output' => $output,
            'completed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    public function markFailed(string $error, array $metadata = []): void
    {
        $this->update([
            'status' => $this->isRetryable() ? 'retrying' : 'failed',
            'error' => $error,
            'completed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }
}