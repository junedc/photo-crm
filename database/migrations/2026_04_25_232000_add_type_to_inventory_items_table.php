<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->string('type', 20)->default('Items')->after('category');
        });

        DB::table('inventory_items')
            ->whereRaw("LOWER(COALESCE(addon_category, '')) = 'action'")
            ->update([
                'type' => 'Action',
                'addon_category' => null,
            ]);

        DB::table('inventory_items')
            ->whereRaw("LOWER(COALESCE(addon_category, '')) = 'items'")
            ->update([
                'type' => 'Items',
                'addon_category' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('inventory_items')
            ->whereNull('addon_category')
            ->whereIn('type', ['Action', 'Items'])
            ->update([
                'addon_category' => DB::raw('type'),
            ]);

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
