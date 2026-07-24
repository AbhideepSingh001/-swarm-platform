<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'swarm_workflow_id',
        'status',
        'context',
        'results',
        'checkpoint',
        'started_at',
        'finished_at',
        'batch_id',        // ← ADD THIS
    ];

    protected $casts = [
        'context' => 'array',
        'results' => 'array',
        'checkpoint' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',

    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(SwarmWorkflow::class, 'swarm_workflow_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    public function markStarted(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markFinished(string $status): void
    {
        $this->update([
            'status' => $status,
            'finished_at' => now(),
        ]);
    }

    public function appendResult(string $stepName, array $result): void
    {
        $results = $this->results ?? [];
        $results[$stepName] = $result;
        $this->update(['results' => $results]);
    }

    public function getStepResult(string $stepName): ?array
    {
        return $this->results[$stepName] ?? null;
    }

    public function getCompletedSteps(): array
    {
        return array_keys($this->results ?? []);
    }
}