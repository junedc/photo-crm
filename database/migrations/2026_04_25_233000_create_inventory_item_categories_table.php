<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->foreignId('inventory_item_category_id')
                ->nullable()
                ->after('type')
                ->constrained('inventory_item_categories')
                ->nullOnDelete();
        });

        $existingCategories = DB::table('inventory_items')
            ->select('tenant_id', 'addon_category')
            ->whereNotNull('tenant_id')
            ->whereNotNull('addon_category')
            ->whereRaw("TRIM(addon_category) <> ''")
            ->distinct()
            ->get();

        foreach ($existingCategories as $category) {
            $existingId = DB::table('inventory_item_categories')
                ->where('tenant_id', $category->tenant_id)
                ->where('name', $category->addon_category)
                ->value('id');

            $categoryId = $existingId ?: DB::table('inventory_item_categories')->insertGetId([
                'tenant_id' => $category->tenant_id,
                'name' => $category->addon_category,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('inventory_items')
                ->where('tenant_id', $category->tenant_id)
                ->where('addon_category', $category->addon_category)
                ->update([
                    'inventory_item_category_id' => $categoryId,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_item_category_id');
        });

        Schema::dropIfExists('inventory_item_categories');
    }
};
