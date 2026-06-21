<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentMessage;
use App\Services\MessageBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MessageBusTest extends TestCase
{
    use RefreshDatabase;

    private MessageBus $messageBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->messageBus = app(MessageBus::class);
    }

    public function test_agent_can_publish_to_channel(): void
    {
        $sender = Agent::factory()->create();
        
        $message = $this->messageBus->publish(
            $sender,
            'swarm.general',
            ['content' => 'Hello swarm!'],
            null,
            'message'
        );

        $this->assertDatabaseHas('agent_messages', [
            'id' => $message->id,
            'sender_id' => $sender->id,
            'channel' => 'swarm.general',
            'status' => 'delivered',
        ]);
    }

    public function test_agent_can_send_direct_message(): void
    {
        $sender = Agent::factory()->create();
        $recipient = Agent::factory()->create();

        $message = $this->messageBus->sendDirect(
            $sender,
            $recipient,
            ['task_id' => 123, 'status' => 'complete'],
            'event'
        );

        $this->assertEquals($recipient->id, $message->recipient_id);
        $this->assertEquals('agent.' . $recipient->id, $message->channel);
        $this->assertEquals('event', $message->type);
    }

    public function test_agent_can_subscribe_and_receive_messages(): void
    {
        $agent = Agent::factory()->create();
        
        $this->messageBus->subscribe($agent, 'task.updates');
        
        $subscribers = $this->messageBus->getSubscribers('task.updates');
        $this->assertContains($agent->id, $subscribers);
    }

    public function test_pull_pending_messages(): void
    {
        $agent = Agent::factory()->create();
        $sender = Agent::factory()->create();

        // Create pending messages
        AgentMessage::factory()->count(3)->create([
            'sender_id' => $sender->id,
            'recipient_id' => $agent->id,
            'channel' => 'swarm.general',
            'status' => 'pending',
        ]);

        $messages = $this->messageBus->pullPending($agent);

        $this->assertCount(3, $messages);
        
        // All should now be delivered
        $this->assertDatabaseMissing('agent_messages', [
            'recipient_id' => $agent->id,
            'status' => 'pending',
        ]);
    }

    public function test_api_can_publish_message(): void
    {
        $sender = Agent::factory()->create();

        $response = $this->postJson('/api/messages/publish', [
            'sender_id' => $sender->id,
            'channel' => 'swarm.general',
            'payload' => ['action' => 'deploy', 'target' => 'production'],
            'type' => 'command',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'swarm.general');
    }

    public function test_api_can_pull_messages(): void
    {
        $agent = Agent::factory()->create();
        $sender = Agent::factory()->create();

        AgentMessage::factory()->count(2)->create([
            'sender_id' => $sender->id,
            'recipient_id' => $agent->id,
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/messages/agent/{$agent->id}/pull");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 2);
    }
}