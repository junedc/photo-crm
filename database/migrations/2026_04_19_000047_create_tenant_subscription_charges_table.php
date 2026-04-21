<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscription_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subscription_name');
            $table->string('billing_period');
            $table->date('period_starts_at');
            $table->date('period_ends_at')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('failure_notified_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('payment_attempts')->default(0);
            $table->text('last_payment_error')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'period_starts_at']);
            $table->index(['status', 'billing_period']);
            $table->index(['status', 'period_starts_at']);
            $table->index(['status', 'next_retry_at']);
            $table->unique(['tenant_id', 'subscription_id', 'period_starts_at'], 'tenant_subscription_charge_period_unique');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscription_charges');
    }
};
