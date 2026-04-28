<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        DB::table('tenants')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function ($tenant): void {
                foreach (['Wedding', 'Birthday', 'Anniversary', 'Others'] as $index => $name) {
                    DB::table('event_types')->updateOrInsert(
                        [
                            'tenant_id' => $tenant->id,
                            'name' => $name,
                        ],
                        [
                            'sort_order' => $index + 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};
