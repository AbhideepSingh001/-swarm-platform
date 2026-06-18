<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consensus_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('swarm_sessions')->onDelete('cascade');
            $table->foreignId('artifact_id')->constrained('artifacts')->onDelete('cascade');
            $table->foreignId('critic_agent_id')->constrained('agents')->onDelete('cascade');
            $table->foreignId('coder_agent_id')->constrained('agents')->onDelete('cascade');
            $table->string('verdict'); // accept, reject, needs_revision
            $table->text('feedback');
            $table->integer('round_number')->default(1);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consensus_logs');
    }
};