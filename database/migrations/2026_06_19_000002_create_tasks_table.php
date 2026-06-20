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
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('task_id');
            $table->string('title');
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->integer('estimated_duration_minutes')->default(0);
            $table->enum('agent_type', ['researcher', 'coder', 'analyst', 'writer', 'reviewer', 'executor']);
            $table->enum('status', ['pending', 'queued', 'running', 'completed', 'failed', 'cancelled'])
                ->default('pending');
            $table->json('depends_on')->nullable();
            $table->json('result')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            $table->index('plan_id');
            $table->index('status');
            $table->index('agent_type');
            $table->index('priority');
            $table->unique(['plan_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};