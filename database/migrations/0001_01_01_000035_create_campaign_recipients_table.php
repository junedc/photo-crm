<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('subscriber_group_id');
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['subscriber_group_id', 'recipient_type', 'recipient_id'], 'campaign_recipients_group_recipient_unique');
            $table->foreign('subscriber_group_id', 'campaign_recipients_subscriber_group_id_foreign')->references('id')->on('subscriber_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
