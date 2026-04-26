<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('expense_category_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('expense_name');
            $table->date('expense_date');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['tenant_id', 'expense_date'], 'expenses_tenant_id_expense_date_index');
            $table->foreign('booking_id', 'expenses_booking_id_foreign')->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('expense_category_id', 'expenses_expense_category_id_foreign')->references('id')->on('expense_categories')->nullOnDelete();
            $table->foreign('tenant_id', 'expenses_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id', 'expenses_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            $table->foreign('vendor_id', 'expenses_vendor_id_foreign')->references('id')->on('tenant_vendors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
