<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedInteger('due_days_before_event')->nullable()->after('duration');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('booking_id')->constrained('inventory_items')->nullOnDelete();
            $table->boolean('is_booking_action')->default(false)->after('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
            $table->dropColumn('is_booking_action');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('due_days_before_event');
        });
    }
};
