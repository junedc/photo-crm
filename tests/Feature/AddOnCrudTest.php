<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddOnCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_addon(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/addons', [
                'sku' => 'ADD-001',
                'name' => 'Guest Book',
                'addon_category' => 'Items',
                'description' => 'Printed guest message station.',
                'unit_price' => '79.00',
                'duration' => '2 hours',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Add-on added.')
            ->assertJsonPath('record.product_code', 'ADD-001')
            ->assertJsonPath('record.addon_category', 'Items')
            ->assertJsonPath('record.is_publicly_displayed', false);

        $this->assertDatabaseHas('inventory_items', [
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-001',
            'name' => 'Guest Book',
            'duration' => '2 hours',
            'addon_category' => 'Items',
            'is_publicly_displayed' => false,
        ]);
    }

    public function test_admin_can_update_addon(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $addon = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-001',
            'name' => 'Guest Book',
            'description' => 'Printed guest message station.',
            'quantity' => 1,
            'unit_price' => 79,
            'duration' => '2 hours',
            'maintenance_status' => 'ready',
        ]);

        $this->actingAs($user)
            ->putJson('http://'.$tenant->slug.'.memoshot.test/addons/'.$addon->id, [
                'sku' => 'ADD-002',
                'name' => 'Audio Guest Book',
                'addon_category' => 'Action',
                'is_publicly_displayed' => true,
                'description' => 'Recorded guest message station.',
                'unit_price' => '129.00',
                'duration' => '4 hours',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Add-on updated.')
            ->assertJsonPath('record.product_code', 'ADD-002')
            ->assertJsonPath('record.addon_category', 'Action')
            ->assertJsonPath('record.is_publicly_displayed', true)
            ->assertJsonPath('record.duration', '4 hours');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $addon->id,
            'sku' => 'ADD-002',
            'name' => 'Audio Guest Book',
            'duration' => '4 hours',
            'addon_category' => 'Action',
            'is_publicly_displayed' => true,
        ]);
    }

    /**
     * @return array{Tenant, User}
     */
    private function tenantUser(): array
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
        ]);

        $user->tenants()->attach($tenant, ['role' => 'owner']);

        return [$tenant, $user];
    }
}
