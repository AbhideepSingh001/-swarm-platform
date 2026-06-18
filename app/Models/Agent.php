<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'memory' => 'array', // JSON column auto-converts to PHP array
    ];

    // An agent belongs to a session
    public function session(): BelongsTo
    {
        return $this->belongsTo(SwarmSession::class, 'session_id');
    }

    // An agent has many assigned tasks
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'agent_id');
    }

    // An agent has many created artifacts
    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class, 'agent_id');
    }

    // Messages this agent sent
    public function sentMessages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'sender_agent_id');
    }

    // Messages this agent received
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'receiver_agent_id');
    }
}