<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_inventory_item', function (Blueprint $table): void {
            $table->decimal('discount_percentage', 5, 2)->default(0)->after('inventory_item_id');
        });

        Schema::table('booking_equipment', function (Blueprint $table): void {
            $table->decimal('discount_percentage', 5, 2)->default(0)->after('equipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_inventory_item', function (Blueprint $table): void {
            $table->dropColumn('discount_percentage');
        });

        Schema::table('booking_equipment', function (Blueprint $table): void {
            $table->dropColumn('discount_percentage');
        });
    }
};
