<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_broadcast_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('orchestration_id')->nullable()->index();
            $table->string('event_type');
            $table->string('channel');
            $table->json('payload');
            $table->json('recipients')->nullable();
            $table->timestamp('broadcast_at');
            $table->boolean('delivered')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'event_type', 'broadcast_at']);
            $table->index(['orchestration_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_broadcast_logs');
    }
};