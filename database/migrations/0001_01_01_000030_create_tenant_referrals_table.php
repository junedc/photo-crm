<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_referrals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('referrer_tenant_id');
            $table->unsignedBigInteger('referred_tenant_id')->nullable();
            $table->string('referral_code', 32);
            $table->string('referred_workspace_name')->nullable();
            $table->string('referred_owner_email')->nullable();
            $table->string('status', 40)->default('registered');
            $table->unsignedBigInteger('referral_status_id')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('referral_code', 'tenant_referrals_referral_code_index');
            $table->unique(['referrer_tenant_id', 'referred_tenant_id'], 'tenant_referrals_referrer_tenant_id_referred_tenant_id_unique');
            $table->index(['referrer_tenant_id', 'status'], 'tenant_referrals_referrer_tenant_id_status_index');
            $table->foreign('referral_status_id', 'tenant_referrals_referral_status_id_foreign')->references('id')->on('workspace_statuses')->nullOnDelete();
            $table->foreign('referred_tenant_id', 'tenant_referrals_referred_tenant_id_foreign')->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('referrer_tenant_id', 'tenant_referrals_referrer_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_referrals');
    }
};
