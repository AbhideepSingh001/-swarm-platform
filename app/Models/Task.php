<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'task_id',
        'title',
        'description',
                'config',        // ADD THIS LINE

        'priority',
        'estimated_duration_minutes',
        'agent_type',
        'status',
        'depends_on',
        'result',
        'retry_count',
        'last_error',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'depends_on' => 'array',
        'result' => 'array',
        'config' => 'array',    // ADD THIS LINE
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    public const AGENT_RESEARCHER = 'researcher';
    public const AGENT_CODER = 'coder';
    public const AGENT_ANALYST = 'analyst';
    public const AGENT_WRITER = 'writer';
    public const AGENT_REVIEWER = 'reviewer';
    public const AGENT_EXECUTOR = 'executor';

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isReady(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $completedDeps = Task::where('plan_id', $this->plan_id)
            ->where('status', self::STATUS_COMPLETED)
            ->pluck('id')
            ->toArray();

        foreach ($this->depends_on as $depId) {
            if (!in_array($depId, $completedDeps)) {
                return false;
            }
        }

        return true;
    }

    public function priorityWeight(): int
    {
        return match ($this->priority) {
            self::PRIORITY_CRITICAL => 4,
            self::PRIORITY_HIGH => 3,
            self::PRIORITY_MEDIUM => 2,
            self::PRIORITY_LOW => 1,
            default => 0,
        };
    }

    public function markRunning(): void
    {
        $this->update(['status' => self::STATUS_RUNNING, 'started_at' => now()]);
    }

    public function dependents(): array
    {
        return Task::where('plan_id', $this->plan_id)
            ->whereJsonContains('depends_on', $this->id)
            ->get()
            ->toArray();
    }
}