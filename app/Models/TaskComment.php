<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TaskComment extends Model
{
    protected $fillable = [
        'task_id', 'commentable_type', 'commentable_id', 'content', 'type', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}