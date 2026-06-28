<?php
// app/Models/TaskExecutionLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskExecutionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_result_id',
        'level',
        'phase',
        'message',
        'context',
        'logged_at',
    ];

    protected $casts = [
        'context' => 'array',
        'logged_at' => 'datetime',
    ];

    public $timestamps = true;

    public function taskResult(): BelongsTo
    {
        return $this->belongsTo(TaskResult::class);
    }

    public function scopeForLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeForPhase($query, string $phase)
    {
        return $query->where('phase', $phase);
    }
}