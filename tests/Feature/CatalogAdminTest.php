<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_only_current_tenant_catalog_records(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $otherTenant = Tenant::factory()->create();

        Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Glam Booth',
            'base_price' => 1200,
            'is_active' => true,
        ]);

        Package::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Hidden Package',
            'base_price' => 999,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/dashboard')
            ->assertOk()
            ->assertSee('data-page="overview"', false)
            ->assertDontSee('Hidden Package');
    }

    public function test_packages_screen_shows_list_and_selected_record_details(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $firstPackage = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Glam Booth',
            'description' => 'First package',
            'base_price' => 1200,
            'is_active' => true,
        ]);

        $selectedPackage = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mirror Booth Luxe',
            'description' => 'Selected package details',
            'base_price' => 1800,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/packages/'.$selectedPackage->id)
            ->assertOk()
            ->assertSee('data-page="packages-detail"', false)
            ->assertSee('Mirror Booth Luxe')
            ->assertSee('Selected package details');

        $this->assertNotSame($firstPackage->id, $selectedPackage->id);
    }

    public function test_package_can_have_multiple_equipment_records(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $firstEquipment = Equipment::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mirror Camera',
            'daily_rate' => 120,
            'maintenance_status' => 'ready',
        ]);

        $secondEquipment = Equipment::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Printer Stand',
            'daily_rate' => 80,
            'maintenance_status' => 'ready',
        ]);

        $this->actingAs($user)
            ->post('http://'.$tenant->slug.'.memoshot.test/packages', [
                'name' => 'Premium Package',
                'description' => 'Full package',
                'base_price' => '1800.00',
                'is_active' => '1',
                'equipment_ids' => [$firstEquipment->id, $secondEquipment->id],
            ])
            ->assertRedirect();

        $package = Package::query()->firstOrFail();

        $this->assertDatabaseHas('equipment', [
            'id' => $firstEquipment->id,
            'package_id' => $package->id,
        ]);

        $this->assertDatabaseHas('equipment', [
            'id' => $secondEquipment->id,
            'package_id' => $package->id,
        ]);
    }

    public function test_package_can_have_assigned_add_ons(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $firstAddOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-101',
            'name' => 'Audio Guest Book',
            'quantity' => 1,
            'unit_price' => 149,
            'duration' => '4 hours',
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $secondAddOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-202',
            'name' => 'Neon Sign',
            'quantity' => 1,
            'unit_price' => 89,
            'duration' => 'Full event',
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $this->actingAs($user)
            ->post('http://'.$tenant->slug.'.memoshot.test/packages', [
                'name' => 'Premium Package',
                'description' => 'Full package',
                'base_price' => '1800.00',
                'is_active' => '1',
                'add_on_ids' => [$firstAddOn->id, $secondAddOn->id],
            ])
            ->assertRedirect();

        $package = Package::query()->firstOrFail();

        $this->assertSame(
            [$firstAddOn->id, $secondAddOn->id],
            $package->addOns()->orderBy('inventory_items.id')->pluck('inventory_items.id')->all(),
        );
    }

    public function test_package_can_have_hour_based_prices(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $this->actingAs($user)
            ->post('http://'.$tenant->slug.'.memoshot.test/packages', [
                'name' => 'Premium Package',
                'description' => 'Full package',
                'base_price' => '1800.00',
                'is_active' => '1',
                'hourly_prices' => [
                    ['hours' => '3.00', 'price' => '1500.00'],
                    ['hours' => '4.50', 'price' => '2100.00'],
                ],
            ])
            ->assertRedirect();

        $package = Package::query()->firstOrFail();

        $this->assertDatabaseHas('package_hourly_prices', [
            'package_id' => $package->id,
            'hours' => '3.00',
            'price' => '1500.00',
        ]);

        $this->assertDatabaseHas('package_hourly_prices', [
            'package_id' => $package->id,
            'hours' => '4.50',
            'price' => '2100.00',
        ]);
    }

    public function test_equipment_screen_shows_list_and_selected_record_details(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $firstEquipment = Equipment::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Printer Booth',
            'category' => 'Printer',
            'daily_rate' => 150,
            'maintenance_status' => 'ready',
        ]);

        $selectedEquipment = Equipment::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mirror Booth Camera',
            'category' => 'Camera',
            'description' => 'Selected equipment details',
            'daily_rate' => 275,
            'maintenance_status' => 'maintenance',
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/equipment/'.$selectedEquipment->id)
            ->assertOk()
            ->assertSee('data-page="equipment-detail"', false)
            ->assertSee('Mirror Booth Camera')
            ->assertSee('Selected equipment details');

        $this->assertNotSame($firstEquipment->id, $selectedEquipment->id);
    }

    public function test_tenant_admin_can_create_packages_and_equipment_with_photos(): void
    {
        Storage::fake('public');

        [$tenant, $user] = $this->tenantUser();

        $this->actingAs($user)
            ->post('http://'.$tenant->slug.'.memoshot.test/packages', [
                'name' => 'Premium Mirror Booth',
                'description' => 'Includes prints and social sharing.',
                'base_price' => '1499.99',
                'is_active' => '1',
                'photo' => UploadedFile::fake()->image('package.jpg'),
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post('http://'.$tenant->slug.'.memoshot.test/equipment', [
                'name' => 'Canon R Booth',
                'category' => 'Camera',
                'serial_number' => 'CAM-001',
                'description' => 'Primary booth camera.',
                'daily_rate' => '250.00',
                'maintenance_status' => 'ready',
                'last_maintained_at' => '2026-03-20',
                'maintenance_notes' => 'Cleaned and calibrated.',
                'photo' => UploadedFile::fake()->image('camera.jpg'),
            ])
            ->assertRedirect();

        $package = Package::query()->firstOrFail();
        $equipment = Equipment::query()->firstOrFail();

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/packages')
            ->assertOk()
            ->assertSee('Premium Mirror Booth');

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/equipment')
            ->assertOk()
            ->assertSee('Canon R Booth');

        $this->assertSame($tenant->id, $package->tenant_id);
        $this->assertSame($tenant->id, $equipment->tenant_id);
        Storage::disk('public')->assertExists($package->photo_path);
        Storage::disk('public')->assertExists($equipment->photo_path);
    }

    public function test_package_can_be_created_via_json_with_photo_upload(): void
    {
        Storage::fake('public');

        [$tenant, $user] = $this->tenantUser();

        $this->actingAs($user)
            ->post('http://'.$tenant->slug.'.memoshot.test/packages', [
                'name' => 'Ajax Package',
                'description' => 'Created over JSON.',
                'base_price' => '1299.00',
                'is_active' => '1',
                'photo' => UploadedFile::fake()->image('ajax-package.jpg'),
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonPath('record.name', 'Ajax Package');

        $package = Package::query()->where('name', 'Ajax Package')->firstOrFail();

        Storage::disk('public')->assertExists($package->photo_path);
    }

    public function test_tenant_admin_can_update_prices_and_maintenance_details(): void
    {
        Storage::fake('public');

        [$tenant, $user] = $this->tenantUser();

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Starter Booth',
            'description' => 'Basic package',
            'base_price' => 899,
            'is_active' => true,
        ]);

        $equipment = Equipment::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Thermal Printer',
            'daily_rate' => 100,
            'maintenance_status' => 'ready',
        ]);

        $packageAssignedEquipment = Equipment::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Booth Camera',
            'daily_rate' => 210,
            'maintenance_status' => 'ready',
        ]);

        $assignedAddOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-303',
            'name' => 'Guestbook Station',
            'quantity' => 1,
            'unit_price' => 199,
            'duration' => 'Full event',
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $this->actingAs($user)
            ->put('http://'.$tenant->slug.'.memoshot.test/packages/'.$package->id, [
                'name' => 'Starter Booth Plus',
                'description' => 'Updated package',
                'base_price' => '999.00',
                'is_active' => '0',
                'equipment_ids' => [$packageAssignedEquipment->id],
                'add_on_ids' => [$assignedAddOn->id],
                'hourly_prices' => [
                    ['hours' => '4.00', 'price' => '999.00'],
                ],
            ])
            ->assertRedirect(route('packages.show', $package, absolute: false));

        $this->actingAs($user)
            ->put('http://'.$tenant->slug.'.memoshot.test/equipment/'.$equipment->id, [
                'name' => 'Thermal Printer',
                'category' => 'Printer',
                'serial_number' => 'PR-778',
                'description' => 'Main event printer',
                'daily_rate' => '135.50',
                'maintenance_status' => 'maintenance',
                'last_maintained_at' => '2026-03-21',
                'maintenance_notes' => 'Roller replacement scheduled.',
            ])
            ->assertRedirect(route('equipment.show', $equipment, absolute: false));

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'name' => 'Starter Booth Plus',
            'base_price' => '999.00',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('equipment', [
            'id' => $equipment->id,
            'daily_rate' => '135.50',
            'maintenance_status' => 'maintenance',
        ]);

        $this->assertDatabaseHas('equipment', [
            'id' => $packageAssignedEquipment->id,
            'package_id' => $package->id,
        ]);

        $this->assertSame([$assignedAddOn->id], $package->fresh()->addOns()->pluck('inventory_items.id')->all());
        $this->assertDatabaseHas('package_hourly_prices', [
            'package_id' => $package->id,
            'hours' => '4.00',
            'price' => '999.00',
        ]);
    }

    public function test_tenant_admin_can_delete_packages_equipment_and_add_ons(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Delete Me Package',
            'base_price' => 1200,
            'is_active' => true,
        ]);

        $equipment = Equipment::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'name' => 'Delete Me Equipment',
            'daily_rate' => 100,
            'maintenance_status' => 'ready',
        ]);

        $addOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'DEL-101',
            'name' => 'Delete Me Add-On',
            'quantity' => 1,
            'unit_price' => 75,
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $package->addOns()->attach($addOn->id);
        $package->hourlyPrices()->create([
            'tenant_id' => $tenant->id,
            'hours' => 3.00,
            'price' => 999.00,
        ]);

        $this->actingAs($user)
            ->delete('http://'.$tenant->slug.'.memoshot.test/packages/'.$package->id, [], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Package deleted.');

        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
        $this->assertDatabaseMissing('package_hourly_prices', ['package_id' => $package->id]);
        $this->assertDatabaseMissing('inventory_item_package', [
            'package_id' => $package->id,
            'inventory_item_id' => $addOn->id,
        ]);

        $this->actingAs($user)
            ->delete('http://'.$tenant->slug.'.memoshot.test/equipment/'.$equipment->id, [], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Equipment deleted.');

        $this->assertDatabaseMissing('equipment', ['id' => $equipment->id]);

        $this->actingAs($user)
            ->delete('http://'.$tenant->slug.'.memoshot.test/addons/'.$addOn->id, [], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Add-on deleted.');

        $this->assertDatabaseMissing('inventory_items', ['id' => $addOn->id]);
    }

    /**
     * @return array{Tenant, User}
     */
    private function tenantUser(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
        ]);

        $user->tenants()->attach($tenant, ['role' => 'owner']);

        return [$tenant, $user];
    }
}
