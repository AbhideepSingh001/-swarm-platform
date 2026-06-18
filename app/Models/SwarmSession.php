<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SwarmSession extends Model
{
    use HasFactory;

    // Columns that can be mass-assigned (security feature)
    protected $fillable = [
        'goal',
        'status',
        'current_phase',
        'started_at',
        'completed_at',
    ];

    // Cast these columns to proper types
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // A session has many agents
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'session_id');
    }

    // A session has many tasks
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'session_id');
    }

    // A session has many artifacts
    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class, 'session_id');
    }

    // A session has many consensus logs
    public function consensusLogs(): HasMany
    {
        return $this->hasMany(ConsensusLog::class, 'session_id');
    }

    // A session has many messages
    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'session_id');
    }
}