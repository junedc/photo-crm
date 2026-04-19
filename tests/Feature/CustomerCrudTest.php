<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_customers_workspace(): void
    {
        [$tenant, $user] = $this->tenantUser();

        Customer::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Morgan Booker',
            'email' => 'morgan@example.com',
            'phone' => '0400111222',
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/customers')
            ->assertOk()
            ->assertSee('data-page="customers"', false)
            ->assertSee('Morgan Booker');
    }

    public function test_admin_can_create_update_and_delete_a_customer(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $createResponse = $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/customers', [
                'full_name' => 'Avery Client',
                'email' => 'avery@example.com',
                'date_of_birth' => null,
                'address' => null,
                'phone' => '0400123123',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Customer added.')
            ->assertJsonPath('record.full_name', 'Avery Client');

        $customerId = $createResponse->json('record.id');

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'tenant_id' => $tenant->id,
            'email' => 'avery@example.com',
            'date_of_birth' => null,
            'address' => null,
        ]);

        $this->actingAs($user)
            ->putJson('http://'.$tenant->slug.'.memoshot.test/customers/'.$customerId, [
                'full_name' => 'Avery Client',
                'email' => 'avery@example.com',
                'date_of_birth' => '1995-07-12',
                'address' => '12 River Street, Brisbane',
                'phone' => '0499888777',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Customer updated.')
            ->assertJsonPath('record.date_of_birth', '1995-07-12')
            ->assertJsonPath('record.address', '12 River Street, Brisbane');

        $customer = Customer::query()->findOrFail($customerId);

        $this->assertSame('1995-07-12', $customer->date_of_birth?->format('Y-m-d'));
        $this->assertSame('12 River Street, Brisbane', $customer->address);
        $this->assertSame('0499888777', $customer->phone);

        $this->actingAs($user)
            ->deleteJson('http://'.$tenant->slug.'.memoshot.test/customers/'.$customerId)
            ->assertOk()
            ->assertJsonPath('message', 'Customer deleted.');

        $this->assertDatabaseMissing('customers', [
            'id' => $customerId,
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
