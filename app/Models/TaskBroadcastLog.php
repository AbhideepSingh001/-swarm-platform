<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskBroadcastLog extends Model
{
    protected $fillable = [
        'task_id', 'orchestration_id', 'event_type', 'channel',
        'payload', 'recipients', 'broadcast_at', 'delivered', 'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'recipients' => 'array',
        'broadcast_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivered' => 'boolean',
    ];
}