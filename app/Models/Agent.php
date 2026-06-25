<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'role',
        'name',
        'status',
        'model',
        'memory',
    ];

    protected $casts = [
        'memory' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(SwarmSession::class, 'session_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'agent_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class, 'agent_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'sender_agent_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'receiver_agent_id');
    }

    public function presence(): HasOne
    {
        return $this->hasOne(AgentPresence::class, 'agent_id');
    }

    // Day 14: Task assignments (polymorphic)
    public function assignments(): MorphMany
    {
        return $this->morphMany(TaskAssignment::class, 'assignable');
    }
}