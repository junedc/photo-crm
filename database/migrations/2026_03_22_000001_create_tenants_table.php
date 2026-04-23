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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('referral_code', 32)->nullable()->unique();
            $table->string('logo_path')->nullable();
            $table->string('theme', 20)->default('dark');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->boolean('subscription_enabled')->default(true);
            $table->timestamp('subscription_disabled_at')->nullable();
            $table->string('platform_stripe_customer_id')->nullable();
            $table->string('platform_stripe_payment_method_id')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();
            $table->decimal('invoice_deposit_percentage', 5, 2)->nullable();
            $table->decimal('travel_free_kilometers', 8, 2)->nullable();
            $table->decimal('travel_fee_per_kilometer', 8, 2)->nullable();
            $table->string('packages_api_key')->nullable();
            $table->text('stripe_secret')->nullable();
            $table->text('stripe_webhook_secret')->nullable();
            $table->string('stripe_currency', 3)->nullable();
            $table->string('quote_prefix', 20)->nullable();
            $table->string('invoice_prefix', 20)->nullable();
            $table->decimal('customer_package_discount_percentage', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
    }
};
