<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_equipment', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discount_id');
            $table->unsignedBigInteger('equipment_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['discount_id', 'equipment_id'], 'discount_equipment_discount_id_equipment_id_unique');
            $table->foreign('discount_id', 'discount_equipment_discount_id_foreign')->references('id')->on('discounts')->cascadeOnDelete();
            $table->foreign('equipment_id', 'discount_equipment_equipment_id_foreign')->references('id')->on('equipment')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_equipment');
    }
};
