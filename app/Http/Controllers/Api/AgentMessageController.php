<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentMessage;
use App\Services\MessageBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AgentMessageController extends Controller
{
    public function __construct(
        private MessageBus $messageBus
    ) {}

    /**
     * Publish a message to a channel.
     */
    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sender_id' => 'required|exists:agents,id',
            'channel' => 'required|string|max:255',
            'payload' => 'required|array',
            'recipient_id' => 'nullable|exists:agents,id',
            'type' => 'nullable|string|in:message,command,event,alert',
        ]);

        $sender = Agent::findOrFail($validated['sender_id']);
        $recipient = isset($validated['recipient_id']) 
            ? Agent::find($validated['recipient_id']) 
            : null;

        $message = $this->messageBus->publish(
            $sender,
            $validated['channel'],
            $validated['payload'],
            $recipient,
            $validated['type'] ?? 'message'
        );

        return response()->json([
            'success' => true,
            'data' => $message->load(['sender', 'recipient']),
        ], 201);
    }

    /**
     * Send a direct message.
     */
    public function sendDirect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sender_id' => 'required|exists:agents,id',
            'recipient_id' => 'required|exists:agents,id',
            'payload' => 'required|array',
            'type' => 'nullable|string|in:message,command,event,alert',
        ]);

        $sender = Agent::findOrFail($validated['sender_id']);
        $recipient = Agent::findOrFail($validated['recipient_id']);

        $message = $this->messageBus->sendDirect(
            $sender,
            $recipient,
            $validated['payload'],
            $validated['type'] ?? 'message'
        );

        return response()->json([
            'success' => true,
            'data' => $message->load(['sender', 'recipient']),
        ], 201);
    }

    /**
     * Pull pending messages for an agent.
     */
    public function pull(Request $request, int $agentId): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'nullable|string',
        ]);

        $agent = Agent::findOrFail($agentId);

        $messages = $this->messageBus->pullPending($agent, $validated['channel'] ?? null);

        return response()->json([
            'success' => true,
            'data' => $messages,
            'count' => count($messages),
        ]);
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(int $messageId): JsonResponse
    {
        $message = AgentMessage::findOrFail($messageId);
        $message->markAsRead();

        return response()->json([
            'success' => true,
            'data' => $message,
        ]);
    }

    /**
     * Subscribe an agent to a channel.
     */
    public function subscribe(Request $request, int $agentId): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string',
        ]);

        $agent = Agent::findOrFail($agentId);
        $this->messageBus->subscribe($agent, $validated['channel']);

        return response()->json([
            'success' => true,
            'message' => "Subscribed to channel: {$validated['channel']}",
        ]);
    }

    /**
     * Unsubscribe an agent from a channel.
     */
    public function unsubscribe(Request $request, int $agentId): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string',
        ]);

        $agent = Agent::findOrFail($agentId);
        $this->messageBus->unsubscribe($agent, $validated['channel']);

        return response()->json([
            'success' => true,
            'message' => "Unsubscribed from channel: {$validated['channel']}",
        ]);
    }

    /**
     * Get message history for an agent.
     */
    public function history(Request $request, int $agentId): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'nullable|string',
            'type' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AgentMessage::where(function ($q) use ($agentId) {
            $q->where('sender_id', $agentId)
              ->orWhere('recipient_id', $agentId)
              ->orWhereNull('recipient_id'); // Broadcast messages
        });

        if ($request->filled('channel')) {
            $query->forChannel($validated['channel']);
        }

        if ($request->filled('type')) {
            $query->where('type', $validated['type']);
        }

        $messages = $query->with(['sender', 'recipient'])
            ->orderByDesc('created_at')
            ->limit($validated['limit'] ?? 50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }
}