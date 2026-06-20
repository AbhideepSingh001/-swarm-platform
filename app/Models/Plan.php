<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'goal',
        'context',
        'status',
        'user_id',
        'total_tasks',
        'estimated_duration_minutes',
        'complexity',
        'metadata',
        'started_at',
        'completed_at',
        'failure_reason',
    ];

    protected $casts = [
        'context' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('id');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ]);
    }

    public function canStart(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function executionOrder(): array
    {
        $tasks = $this->tasks->keyBy('id')->toArray();
        $sorted = [];
        $inDegree = [];
        $adjList = [];

        foreach ($tasks as $id => $task) {
            $inDegree[$id] = 0;
            $adjList[$id] = [];
        }

        foreach ($tasks as $id => $task) {
            foreach ($task['depends_on'] as $depId) {
                if (isset($adjList[$depId])) {
                    $adjList[$depId][] = $id;
                    $inDegree[$id]++;
                }
            }
        }

        $queue = [];
        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $tasks[$current];

            foreach ($adjList[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        return $sorted;
    }
}