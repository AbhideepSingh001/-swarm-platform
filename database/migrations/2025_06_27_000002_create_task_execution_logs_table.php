<?php
// database/migrations/2026_06_28_000002_create_task_execution_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_result_id')->constrained('task_results')->cascadeOnDelete();
            $table->string('level'); // debug, info, warning, error
            $table->string('phase')->nullable(); // initialization, execution, cleanup
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['task_result_id', 'level']);
            $table->index(['logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_execution_logs');
    }
};