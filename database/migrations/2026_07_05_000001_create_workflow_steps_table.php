<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('swarm_workflow_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('agent');
            $table->string('task');
            $table->json('config')->nullable();
            $table->json('depends_on')->nullable();
            $table->integer('order')->default(0);
            $table->integer('max_retries')->default(0);
            $table->timestamps();

            $table->unique(['swarm_workflow_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};