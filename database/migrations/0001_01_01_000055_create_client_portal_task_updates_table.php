<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_task_updates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('task_status_id')->nullable();
            $table->string('customer_email');
            $table->string('action')->default('save_note');
            $table->text('note')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('booking_id', 'client_portal_task_updates_booking_id_foreign')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('task_id', 'client_portal_task_updates_task_id_foreign')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('task_status_id', 'client_portal_task_updates_task_status_id_foreign')->references('id')->on('task_statuses')->nullOnDelete();
            $table->foreign('tenant_id', 'client_portal_task_updates_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_task_updates');
    }
};
