<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_inventory_item', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discount_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['discount_id', 'inventory_item_id'], 'discount_inventory_item_discount_id_inventory_item_id_unique');
            $table->foreign('discount_id', 'discount_inventory_item_discount_id_foreign')->references('id')->on('discounts')->cascadeOnDelete();
            $table->foreign('inventory_item_id', 'discount_inventory_item_inventory_item_id_foreign')->references('id')->on('inventory_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_inventory_item');
    }
};
