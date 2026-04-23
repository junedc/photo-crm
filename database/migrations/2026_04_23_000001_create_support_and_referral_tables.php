<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'referral_code')) {
                $table->string('referral_code', 32)->nullable()->unique()->after('slug');
            }
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('type', 40)->default('bug');
            $table->string('priority', 40)->default('normal');
            $table->string('subject');
            $table->text('description');
            $table->string('status', 40)->default('open');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('tenant_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('referred_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('referral_code', 32);
            $table->string('referred_workspace_name')->nullable();
            $table->string('referred_owner_email')->nullable();
            $table->string('status', 40)->default('registered');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['referrer_tenant_id', 'status']);
            $table->index('referral_code');
            $table->unique(['referrer_tenant_id', 'referred_tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_referrals');
        Schema::dropIfExists('support_tickets');

        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'referral_code')) {
                $table->dropUnique(['referral_code']);
                $table->dropColumn('referral_code');
            }
        });
    }
};
