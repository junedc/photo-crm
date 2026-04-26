<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_inventory_item', function (Blueprint $table): void {
            $table->string('discount_type', 20)->default('percentage')->after('discount_percentage');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
        });

        Schema::table('booking_equipment', function (Blueprint $table): void {
            $table->string('discount_type', 20)->default('percentage')->after('discount_percentage');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('booking_inventory_item', function (Blueprint $table): void {
            $table->dropColumn(['discount_type', 'discount_value']);
        });

        Schema::table('booking_equipment', function (Blueprint $table): void {
            $table->dropColumn(['discount_type', 'discount_value']);
        });
    }
};
