<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->string('name');
            $table->timestamps();

            $table->unique(['tenant_id', 'scope', 'name']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->string('status')->nullable()->after('photo_path');
        });

        DB::table('packages')->select(['id', 'is_active'])->orderBy('id')->chunkById(100, function ($packages): void {
            foreach ($packages as $package) {
                DB::table('packages')
                    ->where('id', $package->id)
                    ->update([
                        'status' => $package->is_active ? 'active' : 'inactive',
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::dropIfExists('workspace_statuses');
    }
};
