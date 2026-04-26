<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_equipment', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('equipment_id');
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->string('discount_type', 20)->default('percentage');
            $table->decimal('discount_value', 10, 2)->default(0.00);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['booking_id', 'equipment_id'], 'booking_equipment_booking_id_equipment_id_unique');
            $table->foreign('booking_id', 'booking_equipment_booking_id_foreign')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('equipment_id', 'booking_equipment_equipment_id_foreign')->references('id')->on('equipment')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_equipment');
    }
};
