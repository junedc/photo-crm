<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignId('task_status_id')->nullable()->after('assigned_to')->constrained('task_statuses')->nullOnDelete();
            $table->text('remarks')->nullable()->after('date_completed');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
            $table->dropConstrainedForeignId('task_status_id');
            $table->dropColumn('remarks');
        });
    }
};
