<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_quotes_workspace(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mirror Booth',
            'description' => 'Full quote package',
            'base_price' => 1200,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Harper Quote',
            'customer_email' => 'harper@example.com',
            'customer_phone' => '0400111000',
            'event_date' => now()->addDays(14)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '18:00',
            'total_hours' => 4,
            'event_location' => 'Brisbane',
            'status' => 'pending',
            'customer_response_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/admin/quotes')
            ->assertOk()
            ->assertSee('data-page="quotes"', false)
            ->assertSee($booking->quote_number)
            ->assertSee('Harper Quote')
            ->assertSee('Mirror Booth');
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
