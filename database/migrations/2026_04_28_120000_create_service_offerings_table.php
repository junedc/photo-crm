<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_offerings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        DB::table('tenant_vendors')
            ->select('tenant_id', 'services_offered', 'service_type')
            ->orderBy('tenant_id')
            ->get()
            ->groupBy('tenant_id')
            ->each(function ($vendors, $tenantId): void {
                $names = collect($vendors)
                    ->flatMap(function ($vendor) {
                        $services = json_decode((string) ($vendor->services_offered ?? '[]'), true);

                        if (! is_array($services) || $services === []) {
                            $services = filled($vendor->service_type ?? null) ? [$vendor->service_type] : [];
                        }

                        return collect($services)
                            ->map(fn ($value) => trim((string) $value))
                            ->filter();
                    })
                    ->unique()
                    ->values();

                foreach ($names as $index => $name) {
                    DB::table('service_offerings')->updateOrInsert(
                        [
                            'tenant_id' => $tenantId,
                            'name' => $name,
                        ],
                        [
                            'sort_order' => $index + 1,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ],
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_offerings');
    }
};
