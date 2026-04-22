<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_items', 'addon_category')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->string('addon_category')->nullable()->after('category');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('inventory_items', 'addon_category')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropColumn('addon_category');
            });
        }
    }
};
