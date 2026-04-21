<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'platform_stripe_customer_id')) {
                $table->string('platform_stripe_customer_id')->nullable()->after('subscription_disabled_at');
            }

            if (! Schema::hasColumn('tenants', 'platform_stripe_payment_method_id')) {
                $table->string('platform_stripe_payment_method_id')->nullable()->after('platform_stripe_customer_id');
            }
        });

        Schema::table('tenant_subscription_charges', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_subscription_charges', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('stripe_payment_intent_id');
            }

            if (! Schema::hasColumn('tenant_subscription_charges', 'failure_notified_at')) {
                $table->timestamp('failure_notified_at')->nullable()->after('reminder_sent_at');
            }

            if (! Schema::hasColumn('tenant_subscription_charges', 'next_retry_at')) {
                $table->timestamp('next_retry_at')->nullable()->after('failure_notified_at');
            }

            if (! Schema::hasColumn('tenant_subscription_charges', 'payment_attempts')) {
                $table->unsignedInteger('payment_attempts')->default(0)->after('paid_at');
            }

            if (! Schema::hasColumn('tenant_subscription_charges', 'last_payment_error')) {
                $table->text('last_payment_error')->nullable()->after('payment_attempts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscription_charges', function (Blueprint $table): void {
            foreach (['last_payment_error', 'payment_attempts', 'next_retry_at', 'failure_notified_at', 'reminder_sent_at'] as $column) {
                if (Schema::hasColumn('tenant_subscription_charges', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('tenants', function (Blueprint $table): void {
            foreach (['platform_stripe_payment_method_id', 'platform_stripe_customer_id'] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
