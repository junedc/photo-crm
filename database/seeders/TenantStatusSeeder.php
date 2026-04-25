<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\TenantStatuses;
use Illuminate\Database\Seeder;

class TenantStatusSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()
            ->each(fn (Tenant $tenant): bool => self::seedTenant($tenant));
    }

    public static function seedTenant(Tenant $tenant): bool
    {
        TenantStatuses::seedDefaults($tenant);

        return true;
    }
}
