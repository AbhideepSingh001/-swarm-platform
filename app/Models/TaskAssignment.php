<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TaskAssignment extends Model
{
    protected $fillable = [
        'task_id', 'assignable_type', 'assignable_id', 'role',
        'assigned_at', 'accepted_at', 'completed_at', 'assignment_note', 'metadata',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function accept(): void
    {
        $this->update(['accepted_at' => now()]);
        broadcast(new \App\Events\Tasks\TaskAssignmentAccepted($this->task, $this));
    }

    public function complete(): void
    {
        $this->update(['completed_at' => now()]);
    }
}