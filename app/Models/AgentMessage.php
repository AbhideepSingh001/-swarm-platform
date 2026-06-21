<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'channel',
        'type',
        'payload',
        'status',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'recipient_id');
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeForRecipient($query, int $agentId)
    {
        return $query->where('recipient_id', $agentId);
    }
}