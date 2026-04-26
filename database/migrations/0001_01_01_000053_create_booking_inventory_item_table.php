<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_inventory_item', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->string('discount_type', 20)->default('percentage');
            $table->decimal('discount_value', 10, 2)->default(0.00);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['booking_id', 'inventory_item_id'], 'booking_inventory_item_booking_id_inventory_item_id_unique');
            $table->foreign('booking_id', 'booking_inventory_item_booking_id_foreign')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('inventory_item_id', 'booking_inventory_item_inventory_item_id_foreign')->references('id')->on('inventory_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_inventory_item');
    }
};
