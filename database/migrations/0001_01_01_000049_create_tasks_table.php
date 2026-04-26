<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->boolean('is_booking_action')->default(false);
            $table->string('task_name');
            $table->decimal('task_duration_hours', 8, 2)->nullable();
            $table->unsignedBigInteger('task_status_id')->nullable();
            $table->string('assignee_type', 30)->nullable();
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->date('due_date')->nullable();
            $table->date('date_started')->nullable();
            $table->date('date_completed')->nullable();
            $table->timestamp('notification_dismissed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['assignee_type', 'assignee_id'], 'tasks_assignee_type_assignee_id_index');
            $table->foreign('booking_id', 'tasks_booking_id_foreign')->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('inventory_item_id', 'tasks_inventory_item_id_foreign')->references('id')->on('inventory_items')->nullOnDelete();
            $table->foreign('task_status_id', 'tasks_task_status_id_foreign')->references('id')->on('task_statuses')->nullOnDelete();
            $table->foreign('tenant_id', 'tasks_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
