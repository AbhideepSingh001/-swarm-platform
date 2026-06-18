<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('swarm_sessions')->onDelete('cascade');
            $table->string('role'); // planner, coder, critic, researcher, executor
            $table->string('name');
            $table->string('status')->default('idle'); // idle, working, waiting
            $table->string('model')->nullable();
            $table->json('memory')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};