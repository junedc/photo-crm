<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_categories', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(1)->after('name');
        });

        Tenant::query()->each(function (Tenant $tenant): void {
            Tenant::seedInventoryItemCategories($tenant);

            $tenant->inventoryItemCategories()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->values()
                ->each(function ($category, int $index): void {
                    DB::table('inventory_item_categories')
                        ->where('id', $category->id)
                        ->update([
                            'sort_order' => $index + 1,
                        ]);
                });
        });
    }

    public function down(): void
    {
        Schema::table('inventory_item_categories', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
