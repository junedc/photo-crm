<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('headline')->nullable();
            $table->text('body');
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('campaign_status_id')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('campaign_status_id', 'campaigns_campaign_status_id_foreign')->references('id')->on('workspace_statuses')->nullOnDelete();
            $table->foreign('template_id', 'campaigns_template_id_foreign')->references('id')->on('templates')->nullOnDelete();
            $table->foreign('tenant_id', 'campaigns_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
