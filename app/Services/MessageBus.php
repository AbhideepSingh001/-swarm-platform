<?php

namespace App\Services;

use App\Events\AgentMessageReceived;
use App\Models\Agent;
use App\Models\AgentMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MessageBus
{
    /**
     * Publish a message to a channel.
     */
    public function publish(
        Agent $sender,
        string $channel,
        array $payload,
        ?Agent $recipient = null,
        string $type = 'message'
    ): AgentMessage {
        $message = AgentMessage::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient?->id,
            'channel' => $channel,
            'type' => $type,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        // Get subscribers for this channel
        $subscribers = $this->getSubscribers($channel);

        // If direct recipient, ensure they're subscribed
        if ($recipient && !in_array($recipient->id, $subscribers)) {
            $subscribers[] = $recipient->id;
        }

        // Broadcast to all subscribers
        foreach ($subscribers as $agentId) {
            // Don't send back to sender unless it's a self-message
            if ($agentId === $sender->id && !$recipient) {
                continue;
            }

            broadcast(new AgentMessageReceived($message, $agentId))->toOthers();
        }

        $message->markAsDelivered();

        Log::info('Message published', [
            'message_id' => $message->id,
            'channel' => $channel,
            'sender' => $sender->id,
            'recipients' => count($subscribers),
        ]);

        return $message;
    }

    /**
     * Subscribe an agent to a channel.
     */
    public function subscribe(Agent $agent, string $channel): void
    {
        $key = "message_bus:channel:{$channel}:subscribers";
        $subscribers = Cache::get($key, []);
        
        if (!in_array($agent->id, $subscribers)) {
            $subscribers[] = $agent->id;
            Cache::put($key, $subscribers, now()->addHours(24));
        }

        Log::info('Agent subscribed to channel', [
            'agent_id' => $agent->id,
            'channel' => $channel,
        ]);
    }

    /**
     * Unsubscribe an agent from a channel.
     */
    public function unsubscribe(Agent $agent, string $channel): void
    {
        $key = "message_bus:channel:{$channel}:subscribers";
        $subscribers = Cache::get($key, []);
        
        $subscribers = array_diff($subscribers, [$agent->id]);
        Cache::put($key, $subscribers, now()->addHours(24));

        Log::info('Agent unsubscribed from channel', [
            'agent_id' => $agent->id,
            'channel' => $channel,
        ]);
    }

    /**
     * Get subscribers for a channel.
     */
    public function getSubscribers(string $channel): array
    {
        return Cache::get("message_bus:channel:{$channel}:subscribers", []);
    }

    /**
     * Pull pending messages for an agent.
     */
    public function pullPending(Agent $agent, ?string $channel = null): array
    {
        $query = AgentMessage::pending()
            ->where(function ($q) use ($agent) {
                $q->where('recipient_id', $agent->id)
                  ->orWhereNull('recipient_id');
            });

        if ($channel) {
            $query->forChannel($channel);
        }

        $messages = $query->orderBy('created_at')->get();

        foreach ($messages as $message) {
            $message->markAsDelivered();
        }

        return $messages->toArray();
    }

    /**
     * Send a direct message between two agents.
     */
    public function sendDirect(Agent $sender, Agent $recipient, array $payload, string $type = 'message'): AgentMessage
    {
        return $this->publish($sender, "agent.{$recipient->id}", $payload, $recipient, $type);
    }

    /**
     * Broadcast to all agents on a channel.
     */
    public function broadcast(Agent $sender, string $channel, array $payload, string $type = 'event'): AgentMessage
    {
        return $this->publish($sender, $channel, $payload, null, $type);
    }
}