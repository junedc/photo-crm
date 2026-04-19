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
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['discount_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_inventory_item');
    }
};
