<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('amounts_are', 30)->default('tax_exclusive')->after('amount_paid');
            $table->text('line_description')->nullable()->after('amounts_are');
            $table->string('tax_rate', 40)->nullable()->after('line_description');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['amounts_are', 'line_description', 'tax_rate']);
        });
    }
};
