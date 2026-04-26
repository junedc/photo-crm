<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Tenant::query()->each(fn (Tenant $tenant) => Tenant::seedExpenseCategories($tenant));
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
