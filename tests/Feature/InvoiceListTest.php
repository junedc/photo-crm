<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_invoices_workspace(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Glam Booth',
            'description' => 'Invoice package',
            'base_price' => 1500,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Jordan Invoice',
            'customer_email' => 'jordan@example.com',
            'customer_phone' => '0400222333',
            'event_date' => now()->addDays(21)->toDateString(),
            'start_time' => '15:00',
            'end_time' => '18:00',
            'total_hours' => 3,
            'event_location' => 'Brisbane',
            'status' => 'confirmed',
        ]);

        $invoice = Invoice::query()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'invoice_number' => 'INV-2026-0001',
            'token' => 'invoice-list-token',
            'total_amount' => 1500,
            'amount_paid' => 450,
            'status' => 'partially_paid',
            'issued_at' => now(),
        ]);

        $invoice->installments()->create([
            'sequence' => 1,
            'label' => 'Deposit',
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 450,
            'status' => 'paid',
        ]);

        $invoice->installments()->create([
            'sequence' => 2,
            'label' => 'Installment 1',
            'due_date' => now()->addDays(30)->toDateString(),
            'amount' => 1050,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/admin/invoices')
            ->assertOk()
            ->assertSee('data-page="invoices"', false)
            ->assertSee('INV-2026-0001')
            ->assertSee('Jordan Invoice')
            ->assertSee('Glam Booth');
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
