<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_installments', function (Blueprint $table): void {
            $table->string('payment_method', 40)->nullable()->after('paid_at');
            $table->string('payment_reference')->nullable()->after('payment_method');
            $table->text('payment_notes')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_installments', function (Blueprint $table): void {
            $table->dropColumn(['payment_method', 'payment_reference', 'payment_notes']);
        });
    }
};
