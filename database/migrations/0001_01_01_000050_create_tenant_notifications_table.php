<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->string('type', 80);
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['tenant_id', 'user_id', 'read_at'], 'tenant_notifications_tenant_id_user_id_read_at_index');
            $table->foreign('booking_id', 'tenant_notifications_booking_id_foreign')->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('task_id', 'tenant_notifications_task_id_foreign')->references('id')->on('tasks')->nullOnDelete();
            $table->foreign('tenant_id', 'tenant_notifications_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id', 'tenant_notifications_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_notifications');
    }
};
