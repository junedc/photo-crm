<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('billing_period')->default('monthly');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('validity_count')->nullable();
            $table->string('validity_unit')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['billing_period', 'is_active']);
        });

        foreach ([
            ['name' => 'Weekly', 'billing_period' => 'weekly', 'validity_count' => 1, 'validity_unit' => 'week'],
            ['name' => 'Monthly', 'billing_period' => 'monthly', 'validity_count' => 1, 'validity_unit' => 'month'],
            ['name' => 'Quarterly', 'billing_period' => 'quarterly', 'validity_count' => 3, 'validity_unit' => 'month'],
            ['name' => 'Yearly', 'billing_period' => 'yearly', 'validity_count' => 1, 'validity_unit' => 'year'],
            ['name' => 'Free for life', 'billing_period' => 'free_for_life', 'validity_count' => null, 'validity_unit' => null],
        ] as $plan) {
            DB::table('subscriptions')->insert([
                ...$plan,
                'price' => 0,
                'currency' => 'USD',
                'description' => $plan['billing_period'] === 'free_for_life' ? 'No expiry.' : null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
