<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('venue')->nullable()->after('event_type');
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->string('venue')->nullable()->after('event_date');
        });

        DB::table('bookings')
            ->whereNull('venue')
            ->update(['venue' => DB::raw('event_location')]);

        DB::table('leads')
            ->whereNull('venue')
            ->update(['venue' => DB::raw('event_location')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('venue');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('venue');
        });
    }
};
