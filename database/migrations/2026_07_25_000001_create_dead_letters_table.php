<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dead_letters', function (Blueprint $table) {
            $table->id();
            $table->string('execution_id')->index();
            $table->string('step_id')->index();
            $table->string('agent_id')->index();
            $table->string('failure_category')->index(); // timeout, syntax_error, agent_error, network, unknown
            $table->text('error_message');
            $table->json('error_trace');
            $table->json('step_config');
            $table->json('context');
            $table->integer('retry_count')->default(0);
            $table->timestamp('failed_at');
            $table->enum('status', ['open', 'retrying', 'resolved', 'dismissed'])->default('open');
            $table->string('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'failure_category']);
            $table->index(['execution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dead_letters');
    }
};
