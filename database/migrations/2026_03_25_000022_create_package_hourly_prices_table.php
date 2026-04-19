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
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('hours');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['package_id', 'hours']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_hourly_prices');
    }
};
