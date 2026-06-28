<?php
// app/Models/Artifact.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Artifact extends Model
{
    use HasFactory;

    protected $table = 'task_artifacts';  // <-- CRITICAL FIX

    protected $fillable = [
        'task_result_id',
        'name',
        'type',
        'mime_type',
        'disk',
        'path',
        'size_bytes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size_bytes' => 'integer',
    ];

    public function taskResult(): BelongsTo
    {
        return $this->belongsTo(TaskResult::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getContent(): ?string
    {
        if (!Storage::disk($this->disk)->exists($this->path)) {
            return null;
        }

        return Storage::disk($this->disk)->get($this->path);
    }

    public function deleteFile(): bool
    {
        if (Storage::disk($this->disk)->exists($this->path)) {
            return Storage::disk($this->disk)->delete($this->path);
        }

        return true;
    }

    protected static function booted(): void
    {
        static::deleting(function (Artifact $artifact) {
            $artifact->deleteFile();
        });
    }
}