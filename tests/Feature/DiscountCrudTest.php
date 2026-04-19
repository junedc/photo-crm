<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_discounts_workspace(): void
    {
        [$tenant, $user] = $this->tenantUser();

        Discount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'SAVE15',
            'name' => 'Autumn Special',
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-04-30',
            'discount_type' => 'percentage',
            'discount_value' => 15,
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/discounts')
            ->assertOk()
            ->assertSee('data-page="discounts"', false)
            ->assertSee('SAVE15')
            ->assertSee('Autumn Special');
    }

    public function test_admin_can_create_update_and_delete_a_discount(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Wedding Gold',
            'description' => 'Package',
            'base_price' => 1200,
        ]);

        $createResponse = $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/discounts', [
                'code' => 'STALL100',
                'name' => 'Launch Offer',
                'starts_at' => '2026-04-10',
                'ends_at' => '2026-05-10',
                'discount_type' => 'fixed_amount',
                'discount_value' => 100,
                'package_ids' => [$package->id],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Discount added.')
            ->assertJsonPath('record.code', 'STALL100');

        $discountId = $createResponse->json('record.id');

        $this->assertDatabaseHas('discounts', [
            'id' => $discountId,
            'tenant_id' => $tenant->id,
            'code' => 'STALL100',
            'discount_type' => 'fixed_amount',
        ]);

        $this->assertDatabaseHas('discount_package', [
            'discount_id' => $discountId,
            'package_id' => $package->id,
        ]);

        $this->actingAs($user)
            ->putJson('http://'.$tenant->slug.'.memoshot.test/discounts/'.$discountId, [
                'code' => 'STALL25',
                'name' => 'Updated Offer',
                'starts_at' => '2026-04-15',
                'ends_at' => '2026-05-15',
                'discount_type' => 'percentage',
                'discount_value' => 25,
                'package_ids' => [$package->id],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Discount updated.')
            ->assertJsonPath('record.discount_type', 'percentage')
            ->assertJsonPath('record.discount_value', '25.00');

        $this->assertDatabaseHas('discounts', [
            'id' => $discountId,
            'code' => 'STALL25',
            'discount_type' => 'percentage',
        ]);

        $this->actingAs($user)
            ->deleteJson('http://'.$tenant->slug.'.memoshot.test/discounts/'.$discountId)
            ->assertOk()
            ->assertJsonPath('message', 'Discount deleted.');

        $this->assertDatabaseMissing('discounts', [
            'id' => $discountId,
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
