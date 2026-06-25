<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        $existingColumns = Schema::getColumnListing('tasks');

        Schema::table('tasks', function (Blueprint $table) use ($existingColumns) {
            if (!in_array('creator_id', $existingColumns)) {
                $table->foreignId('creator_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
            if (!in_array('orchestration_id', $existingColumns)) {
                $table->string('orchestration_id')->nullable()->index()->after('creator_id');
            }
            if (!in_array('parent_task_id', $existingColumns)) {
                $table->foreignId('parent_task_id')->nullable()->after('orchestration_id')
                    ->constrained('tasks')->nullOnDelete();
            }
            if (!in_array('task_type', $existingColumns)) {
                $table->string('task_type')->default('custom')->after('status');
            }
            if (!in_array('progress_percent', $existingColumns)) {
                $table->integer('progress_percent')->default(0)->after('task_type');
            }
            if (!in_array('max_retries', $existingColumns)) {
                $table->integer('max_retries')->default(3)->after('retry_count');
            }
            if (!in_array('scheduled_at', $existingColumns)) {
                $table->timestamp('scheduled_at')->nullable()->after('failed_at');
            }
            if (!in_array('deadline_at', $existingColumns)) {
                $table->timestamp('deadline_at')->nullable()->after('scheduled_at');
            }
            if (!in_array('actual_duration_minutes', $existingColumns)) {
                $table->integer('actual_duration_minutes')->nullable()->after('estimated_duration_minutes');
            }
            if (!in_array('metadata', $existingColumns)) {
                $table->json('metadata')->nullable()->after('config');
            }
            if (!in_array('deleted_at', $existingColumns)) {
                $table->softDeletes()->after('updated_at');
            }
            
            // Make existing NOT NULL columns nullable for flexibility
            $table->string('description')->nullable()->change();
            $table->string('agent_type')->nullable()->change();
            $table->foreignId('plan_id')->nullable()->change();
            $table->integer('estimated_duration_minutes')->nullable()->change();
            
            $indexes = array_column(Schema::getIndexes('tasks'), 'name');
            if (!in_array('tasks_orchestration_id_status_index', $indexes)) {
                $table->index(['orchestration_id', 'status']);
            }
            if (!in_array('tasks_parent_task_id_status_index', $indexes)) {
                $table->index(['parent_task_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $existingColumns = Schema::getColumnListing('tasks');
            
            $columnsToDrop = array_filter([
                'creator_id', 'orchestration_id', 'parent_task_id', 'task_type', 'progress_percent',
                'max_retries', 'scheduled_at', 'deadline_at', 'actual_duration_minutes',
                'metadata', 'deleted_at'
            ], fn($col) => in_array($col, $existingColumns));
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
            
            try { $table->dropIndex(['orchestration_id', 'status']); } catch (\Exception $e) {}
            try { $table->dropIndex(['parent_task_id', 'status']); } catch (\Exception $e) {}
        });
    }
};