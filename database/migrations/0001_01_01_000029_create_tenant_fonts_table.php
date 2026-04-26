<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_fonts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('family');
            $table->unsignedSmallInteger('weight')->default(400);
            $table->string('style', 20)->default('normal');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('extension', 10);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['tenant_id', 'family', 'weight', 'style'], 'tenant_fonts_tenant_id_family_weight_style_unique');
            $table->foreign('tenant_id', 'tenant_fonts_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_fonts');
    }
};
