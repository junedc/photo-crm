<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'theme')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('theme');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'theme')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('theme', 20)->default('dark')->after('logo_path');
        });
    }
};
