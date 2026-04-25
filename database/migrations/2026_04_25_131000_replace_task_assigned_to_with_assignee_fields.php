<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('assignee_type', 30)->nullable()->after('task_status_id');
            $table->unsignedBigInteger('assignee_id')->nullable()->after('assignee_type');
            $table->index(['assignee_type', 'assignee_id']);
        });

        DB::table('tasks')
            ->whereNotNull('assigned_to')
            ->update([
                'assignee_type' => 'user',
                'assignee_id' => DB::raw('assigned_to'),
            ]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('task_duration_hours')->constrained('users')->nullOnDelete();
        });

        DB::table('tasks')
            ->where('assignee_type', 'user')
            ->whereNotNull('assignee_id')
            ->update([
                'assigned_to' => DB::raw('assignee_id'),
            ]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['assignee_type', 'assignee_id']);
            $table->dropColumn(['assignee_type', 'assignee_id']);
        });
    }
};
