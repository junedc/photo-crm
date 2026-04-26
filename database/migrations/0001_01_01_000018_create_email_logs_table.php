<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_type')->default('to');
            $table->string('subject');
            $table->longText('html_content');
            $table->longText('text_content')->nullable();
            $table->longText('attachments')->nullable();
            $table->string('mailable_class')->nullable();
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('status')->default('sent');
            $table->unsignedBigInteger('email_tracking_status_id')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('related_email_log_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('email_tracking_status_id', 'email_logs_email_tracking_status_id_foreign')->references('id')->on('workspace_statuses')->nullOnDelete();
            $table->foreign('related_email_log_id', 'email_logs_related_email_log_id_foreign')->references('id')->on('email_logs')->nullOnDelete();
            $table->foreign('tenant_id', 'email_logs_tenant_id_foreign')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
