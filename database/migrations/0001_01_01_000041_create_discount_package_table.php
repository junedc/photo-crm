<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_package', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discount_id');
            $table->unsignedBigInteger('package_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['discount_id', 'package_id'], 'discount_package_discount_id_package_id_unique');
            $table->foreign('discount_id', 'discount_package_discount_id_foreign')->references('id')->on('discounts')->cascadeOnDelete();
            $table->foreign('package_id', 'discount_package_package_id_foreign')->references('id')->on('packages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_package');
    }
};
