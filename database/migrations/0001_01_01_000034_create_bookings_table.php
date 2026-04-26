<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('package_id');
            $table->decimal('package_price', 10, 2)->nullable();
            $table->unsignedBigInteger('discount_id')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->string('booking_kind')->default('customer');
            $table->string('entry_name')->nullable();
            $table->text('entry_description')->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->string('event_type')->nullable();
            $table->string('venue')->nullable();
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('total_hours', 5, 2)->default(0.00);
            $table->string('event_location');
            $table->decimal('travel_distance_km', 10, 2)->default(0.00);
            $table->decimal('travel_fee', 10, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('booking_status_id')->nullable();
            $table->char('quote_token', 36)->nullable();
            $table->string('quote_number')->nullable();
            $table->string('customer_response_status')->default('pending');
            $table->unsignedBigInteger('quote_response_status_id')->nullable();
            $table->timestamp('customer_responded_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('quote_number', 'bookings_quote_number_unique');
            $table->unique('quote_token', 'bookings_quote_token_unique');
            $table->foreign('booking_status_id', 'bookings_booking_status_id_foreign')->references('id')->on('workspace_statuses')->nullOnDelete();
            $table->foreign('customer_id', 'bookings_customer_id_foreign')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('discount_id', 'bookings_discount_id_foreign')->references('id')->on('discounts')->nullOnDelete();
            $table->foreign('package_id', 'bookings_package_id_foreign')->references('id')->on('packages')->cascadeOnDelete();
            $table->foreign('quote_response_status_id', 'bookings_quote_response_status_id_foreign')->references('id')->on('workspace_statuses')->nullOnDelete();
            $table->foreign('tenant_id', 'bookings_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
