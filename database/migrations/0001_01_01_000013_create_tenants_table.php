<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('referral_code', 32)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('theme', 20)->default('dark');
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedBigInteger('subscription_id')->nullable();
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
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('referral_code', 'tenants_referral_code_unique');
            $table->unique('slug', 'tenants_slug_unique');
            $table->foreign('subscription_id', 'tenants_subscription_id_foreign')->references('id')->on('subscriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
