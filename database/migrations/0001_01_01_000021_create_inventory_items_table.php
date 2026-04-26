<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('type', 20)->default('Items');
            $table->unsignedBigInteger('inventory_item_category_id')->nullable();
            $table->string('addon_category')->nullable();
            $table->boolean('is_publicly_displayed')->default(false);
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->string('duration')->nullable();
            $table->unsignedInteger('due_days_before_event')->nullable();
            $table->string('maintenance_status')->default('ready');
            $table->date('last_maintained_at')->nullable();
            $table->text('maintenance_notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('inventory_item_category_id', 'inventory_items_inventory_item_category_id_foreign')->references('id')->on('inventory_item_categories')->nullOnDelete();
            $table->foreign('tenant_id', 'inventory_items_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
