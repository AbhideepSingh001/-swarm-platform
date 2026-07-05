<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'swarm_workflow_id',
        'name',
        'agent',
        'task',
        'config',
        'depends_on',
        'order',
        'max_retries',
    ];

    protected $casts = [
        'config' => 'array',
        'depends_on' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(SwarmWorkflow::class, 'swarm_workflow_id');
    }
}