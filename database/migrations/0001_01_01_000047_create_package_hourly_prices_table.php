<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_hourly_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('package_id');
            $table->unsignedInteger('hours');
            $table->decimal('price', 10, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['package_id', 'hours'], 'package_hourly_prices_package_id_hours_unique');
            $table->foreign('package_id', 'package_hourly_prices_package_id_foreign')->references('id')->on('packages')->cascadeOnDelete();
            $table->foreign('tenant_id', 'package_hourly_prices_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_hourly_prices');
    }
};
