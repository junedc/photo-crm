<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('billing_period')->default('monthly');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('validity_count')->nullable();
            $table->string('validity_unit')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['billing_period', 'is_active'], 'subscriptions_billing_period_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
