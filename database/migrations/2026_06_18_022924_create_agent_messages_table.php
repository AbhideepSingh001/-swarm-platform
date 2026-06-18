<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('swarm_sessions')->onDelete('cascade');
            $table->foreignId('sender_agent_id')->constrained('agents')->onDelete('cascade');
            $table->foreignId('receiver_agent_id')->nullable()->constrained('agents')->onDelete('cascade');
            $table->string('message_type'); // task_assignment, feedback, status_update, consensus
            $table->text('content');
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};