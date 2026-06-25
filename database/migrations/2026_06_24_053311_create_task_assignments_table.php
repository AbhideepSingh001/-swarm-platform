<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->morphs('assignable');
            $table->enum('role', ['primary', 'secondary', 'reviewer', 'observer'])->default('primary');
            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('assignment_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'assignable_type', 'assignable_id', 'role']);
            $table->index(['assignable_type', 'assignable_id', 'assigned_at']);
            $table->index(['task_id', 'role']);
            $table->index(['accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
    }
};