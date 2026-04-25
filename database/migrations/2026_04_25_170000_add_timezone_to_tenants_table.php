<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('timezone', 64)->default(config('app.timezone', 'UTC'))->after('theme');
        });

        DB::table('tenants')
            ->whereNull('timezone')
            ->update([
                'timezone' => config('app.timezone', 'UTC'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
