<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('headline')->nullable();
            $table->text('html_body');
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('headline')->nullable();
            $table->text('body');
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriber_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscriber_group_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');
            $table->timestamps();

            $table->unique(['subscriber_group_id', 'recipient_type', 'recipient_id'], 'campaign_recipients_group_recipient_unique');
        });

        Schema::create('campaign_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('token')->unique();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'email'], 'campaign_results_campaign_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_results');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('subscriber_groups');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('templates');
    }
};
