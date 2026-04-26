<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ticket_number');
            $table->string('type', 40)->default('bug');
            $table->string('priority', 40)->default('normal');
            $table->string('subject');
            $table->text('description');
            $table->string('status', 40)->default('open');
            $table->unsignedBigInteger('support_status_id')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['tenant_id', 'created_at'], 'support_tickets_tenant_id_created_at_index');
            $table->index(['tenant_id', 'status'], 'support_tickets_tenant_id_status_index');
            $table->unique('ticket_number', 'support_tickets_ticket_number_unique');
            $table->foreign('support_status_id', 'support_tickets_support_status_id_foreign')->references('id')->on('workspace_statuses')->nullOnDelete();
            $table->foreign('tenant_id', 'support_tickets_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id', 'support_tickets_user_id_foreign')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
