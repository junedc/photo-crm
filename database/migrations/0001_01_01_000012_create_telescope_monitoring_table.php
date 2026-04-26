<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telescope_monitoring', function (Blueprint $table): void {
            $table->string('tag')->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telescope_monitoring');
    }
};
