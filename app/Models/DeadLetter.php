<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeadLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'execution_id',
        'step_id',
        'agent_id',
        'failure_category',
        'error_message',
        'error_trace',
        'step_config',
        'context',
        'retry_count',
        'failed_at',
        'status',
        'resolution',
        'resolved_at',
    ];

    protected $casts = [
        'error_trace' => 'array',
        'step_config' => 'array',
        'context' => 'array',
        'failed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeForExecution($query, string $executionId)
    {
        return $query->where('execution_id', $executionId);
    }

    public function scopeForAgent($query, string $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function markRetrying(): void
    {
        $this->update(['status' => 'retrying']);
    }

    public function markResolved(string $resolution = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolution' => $resolution,
            'resolved_at' => now(),
        ]);
    }

    public function markDismissed(string $reason = null): void
    {
        $this->update([
            'status' => 'dismissed',
            'resolution' => $reason,
            'resolved_at' => now(),
        ]);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    // In app/Models/DeadLetter.php

public function scopeWithFilters($query, array $filters)
{
    if (!empty($filters['status'])) {
        $query->where('status', $filters['status']);
    }

    if (!empty($filters['failure_category'])) {
        $query->where('failure_category', $filters['failure_category']);
    }

    if (!empty($filters['agent_id'])) {
        $query->where('agent_id', $filters['agent_id']);
    }

    if (!empty($filters['execution_id'])) {
        $query->where('execution_id', $filters['execution_id']);
    }

    return $query;
}
}
