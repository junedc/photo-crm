<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item_package', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['package_id', 'inventory_item_id'], 'inventory_package_unique');
            $table->foreign('inventory_item_id', 'inventory_item_package_inventory_item_id_foreign')->references('id')->on('inventory_items')->cascadeOnDelete();
            $table->foreign('package_id', 'inventory_item_package_package_id_foreign')->references('id')->on('packages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_package');
    }
};
