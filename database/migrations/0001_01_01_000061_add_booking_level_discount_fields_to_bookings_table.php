<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('booking_discount_type', 20)->nullable()->after('discount_amount');
            $table->decimal('booking_discount_value', 10, 2)->default(0.00)->after('booking_discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['booking_discount_type', 'booking_discount_value']);
        });
    }
};
