<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'driver')) {
                $table->string('driver')->nullable()->after('agent_type');
            }
            if (!Schema::hasColumn('tasks', 'payload')) {
                $table->json('payload')->nullable()->after('driver');
            }
            if (!Schema::hasColumn('tasks', 'output')) {
                $table->longText('output')->nullable()->after('result');
            }
            if (!Schema::hasColumn('tasks', 'attempts')) {
                $table->unsignedSmallInteger('attempts')->default(0)->after('retry_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $columns = ['driver', 'payload', 'output', 'attempts'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('tasks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};