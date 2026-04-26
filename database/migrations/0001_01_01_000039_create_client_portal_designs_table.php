<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_designs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('booking_id');
            $table->string('customer_email');
            $table->string('title')->default('Client design draft');
            $table->json('design_data')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['tenant_id', 'booking_id'], 'client_portal_designs_tenant_id_booking_id_unique');
            $table->foreign('booking_id', 'client_portal_designs_booking_id_foreign')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('tenant_id', 'client_portal_designs_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_designs');
    }
};
