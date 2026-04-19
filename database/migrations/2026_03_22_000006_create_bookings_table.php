<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->decimal('package_price', 10, 2)->nullable();
            $table->foreignId('discount_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('booking_kind')->default('customer');
            $table->string('entry_name')->nullable();
            $table->text('entry_description')->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->string('event_type')->nullable();
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('total_hours', 5, 2)->default(0);
            $table->string('event_location');
            $table->decimal('travel_distance_km', 10, 2)->default(0);
            $table->decimal('travel_fee', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->uuid('quote_token')->nullable()->unique();
            $table->string('quote_number')->nullable()->unique();
            $table->string('customer_response_status')->default('pending');
            $table->timestamp('customer_responded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
