<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_active_packages_for_the_requested_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
            'name' => 'PhotoBoo Events',
            'packages_api_key' => 'secret-packages-key',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mirror Booth',
            'description' => 'Luxury mirror booth package.',
            'base_price' => 1200,
            'is_active' => true,
        ]);

        $package->hourlyPrices()->create([
            'tenant_id' => $tenant->id,
            'hours' => 3,
            'price' => 1200,
        ]);

        $addOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-100',
            'name' => 'Guest Book',
            'description' => 'Printed keepsake.',
            'quantity' => 1,
            'unit_price' => 150,
            'duration' => '3 hours',
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $package->addOns()->attach($addOn);

        Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Inactive Package',
            'base_price' => 800,
            'is_active' => false,
        ]);

        $this->withHeaders([
            'X-Tenant-Api-Key' => 'secret-packages-key',
        ])->getJson('http://memoshot.test/api/public/packages?tenant='.$tenant->slug)
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'photoboo')
            ->assertJsonCount(1, 'packages')
            ->assertJsonPath('packages.0.name', 'Mirror Booth')
            ->assertJsonPath('packages.0.add_ons.0.name', 'Guest Book')
            ->assertJsonPath('packages.0.booking_url', 'http://photoboo.memoshot.test/bookings/create?package_id='.$package->id);
    }

    public function test_it_requires_a_tenant_slug(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
            'packages_api_key' => 'secret-packages-key',
        ]);

        $this->withHeaders([
            'X-Tenant-Api-Key' => $tenant->packages_api_key,
        ])->getJson('http://memoshot.test/api/public/packages')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenant']);
    }

    public function test_it_rejects_requests_without_a_valid_api_key(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
            'packages_api_key' => 'secret-packages-key',
        ]);

        $this->getJson('http://memoshot.test/api/public/packages?tenant='.$tenant->slug)
            ->assertStatus(401);

        $this->withHeaders([
            'X-Tenant-Api-Key' => 'wrong-key',
        ])->getJson('http://memoshot.test/api/public/packages?tenant='.$tenant->slug)
            ->assertStatus(401);
    }

    public function test_it_rejects_requests_when_workspace_has_no_packages_api_key(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
            'packages_api_key' => null,
        ]);

        $this->withHeaders([
            'X-Tenant-Api-Key' => 'anything',
        ])->getJson('http://memoshot.test/api/public/packages?tenant='.$tenant->slug)
            ->assertStatus(403);
    }
}
