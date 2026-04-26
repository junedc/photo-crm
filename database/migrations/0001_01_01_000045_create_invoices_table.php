<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('booking_id');
            $table->string('invoice_number');
            $table->string('token');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0.00);
            $table->string('status')->default('issued');
            $table->unsignedBigInteger('invoice_status_id')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('invoice_number', 'invoices_invoice_number_unique');
            $table->unique('token', 'invoices_token_unique');
            $table->foreign('booking_id', 'invoices_booking_id_foreign')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('invoice_status_id', 'invoices_invoice_status_id_foreign')->references('id')->on('workspace_statuses')->nullOnDelete();
            $table->foreign('tenant_id', 'invoices_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
