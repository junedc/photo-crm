<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_vendors', function (Blueprint $table): void {
            $table->string('address')->nullable()->after('name');
            $table->string('mobile_number', 50)->nullable()->after('address');
            $table->json('services_offered')->nullable()->after('service_type');
            $table->boolean('is_active')->default(true)->after('services_offered');
        });

        DB::table('tenant_vendors')
            ->select('id', 'service_type')
            ->orderBy('id')
            ->get()
            ->each(function ($vendor): void {
                $services = filled($vendor->service_type) ? [trim((string) $vendor->service_type)] : [];

                DB::table('tenant_vendors')
                    ->where('id', $vendor->id)
                    ->update([
                        'services_offered' => json_encode($services),
                        'is_active' => true,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('tenant_vendors', function (Blueprint $table): void {
            $table->dropColumn(['address', 'mobile_number', 'services_offered', 'is_active']);
        });
    }
};
