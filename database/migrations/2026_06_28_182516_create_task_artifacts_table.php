<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_result_id')->constrained('task_results')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('mime_type');
            $table->string('disk');
            $table->string('path');
            $table->unsignedInteger('size_bytes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['task_result_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_artifacts');
    }
};