<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_installments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedInteger('sequence');
            $table->string('label');
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('invoice_installment_status_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('invoice_id', 'invoice_installments_invoice_id_foreign')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('invoice_installment_status_id', 'invoice_installments_invoice_installment_status_id_foreign')->references('id')->on('workspace_statuses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_installments');
    }
};
