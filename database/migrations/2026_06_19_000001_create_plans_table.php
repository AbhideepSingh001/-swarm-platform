<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('goal');
            $table->json('context')->nullable();
            $table->enum('status', ['pending', 'running', 'paused', 'completed', 'failed', 'cancelled'])
                ->default('pending');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('total_tasks')->default(0);
            $table->integer('estimated_duration_minutes')->default(0);
            $table->enum('complexity', ['low', 'medium', 'high'])->default('medium');
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};