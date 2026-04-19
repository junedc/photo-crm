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
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['package_id', 'inventory_item_id'],
                'inventory_package_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_package');
    }
};
