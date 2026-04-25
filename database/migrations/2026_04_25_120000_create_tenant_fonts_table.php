<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_fonts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('family');
            $table->unsignedSmallInteger('weight')->default(400);
            $table->string('style', 20)->default('normal');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('extension', 10);
            $table->timestamps();

            $table->unique(['tenant_id', 'family', 'weight', 'style']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_fonts');
    }
};
