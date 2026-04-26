<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('discount_type');
            $table->decimal('discount_value', 10, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['tenant_id', 'code'], 'discounts_tenant_id_code_unique');
            $table->foreign('tenant_id', 'discounts_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
