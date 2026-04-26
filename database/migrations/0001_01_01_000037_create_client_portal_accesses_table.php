<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_accesses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('granted_by_user_id')->nullable();
            $table->string('customer_email');
            $table->string('customer_name')->nullable();
            $table->char('invite_token', 36);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('invite_token', 'client_portal_accesses_invite_token_unique');
            $table->unique(['tenant_id', 'customer_email'], 'client_portal_accesses_tenant_id_customer_email_unique');
            $table->foreign('booking_id', 'client_portal_accesses_booking_id_foreign')->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('granted_by_user_id', 'client_portal_accesses_granted_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            $table->foreign('tenant_id', 'client_portal_accesses_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_accesses');
    }
};
