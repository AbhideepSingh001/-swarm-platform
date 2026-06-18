<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'agent_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'code',
        'feedback',
        'retry_count',
    ];

    protected $casts = [
        'priority' => 'integer',
        'retry_count' => 'integer',
    ];

    // A task belongs to a session
    public function session(): BelongsTo
    {
        return $this->belongsTo(SwarmSession::class, 'session_id');
    }

    // A task belongs to an agent (nullable)
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    // A task has many artifacts
    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class, 'task_id');
    }
}