<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('campaign_recipient_id')->nullable();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('token');
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['campaign_id', 'email'], 'campaign_results_campaign_email_unique');
            $table->unique('token', 'campaign_results_token_unique');
            $table->foreign('campaign_id', 'campaign_results_campaign_id_foreign')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('campaign_recipient_id', 'campaign_results_campaign_recipient_id_foreign')->references('id')->on('campaign_recipients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_results');
    }
};
