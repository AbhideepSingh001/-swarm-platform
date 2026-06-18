<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('swarm_sessions')->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained('agents')->onDelete('set null');
            $table->string('title');
            $table->text('description');
            $table->string('type'); // planning, coding, review, research, execution
            $table->string('status')->default('pending'); // pending, in_progress, completed, failed
            $table->integer('priority')->default(2); // 1=high, 2=medium, 3=low
            $table->text('code')->nullable();
            $table->text('feedback')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};