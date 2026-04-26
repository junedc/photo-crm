<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_replies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('message');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['tenant_id', 'support_ticket_id'], 'support_ticket_replies_tenant_id_support_ticket_id_index');
            $table->foreign('support_ticket_id', 'support_ticket_replies_support_ticket_id_foreign')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('tenant_id', 'support_ticket_replies_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id', 'support_ticket_replies_user_id_foreign')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_replies');
    }
};
