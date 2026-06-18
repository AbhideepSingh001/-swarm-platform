<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swarm_sessions', function (Blueprint $table) {
            $table->id();
            $table->text('goal');
            $table->string('status')->default('pending'); // pending, running, completed, failed, deadlocked
            $table->string('current_phase')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps(); // creates created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swarm_sessions');
    }
};