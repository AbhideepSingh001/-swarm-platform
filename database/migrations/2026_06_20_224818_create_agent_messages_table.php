<?php
// database/migrations/2026_06_21_000001_create_agent_messages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('agents')->cascadeOnDelete();
            // Null recipient = broadcast to channel subscribers
            $table->string('channel'); // e.g., 'swarm.general', 'task.updates'
            $table->string('type')->default('message'); // message, command, event, alert
            $table->json('payload'); // flexible message content
            $table->string('status')->default('pending'); // pending, delivered, read, failed
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index(['recipient_id', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};