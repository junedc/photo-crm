<?php

namespace Tests\Feature;

use App\Mail\InvoiceIssuedMail;
use App\Models\Booking;
use App\Models\Discount;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_installment_invoice_for_booking(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Deluxe Booth',
            'base_price' => 1000,
            'is_active' => true,
        ]);

        $addOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-1',
            'name' => 'Guestbook',
            'quantity' => 1,
            'unit_price' => 200,
            'duration' => 'Full event',
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Jamie Customer',
            'customer_email' => 'jamie@example.com',
            'customer_phone' => '0400000000',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'Brisbane',
            'travel_distance_km' => 15,
            'travel_fee' => 200,
            'status' => 'pending',
        ]);
        $booking->addOns()->sync([$addOn->id]);

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/admin/bookings/'.$booking->id.'/invoice', [
                'installment_count' => 3,
                'deposit_percentage' => 25,
                'first_due_date' => now()->addWeek()->toDateString(),
                'interval_days' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('record.total_amount', '1400.00')
            ->assertJsonPath('record.installments.0.label', 'Deposit')
            ->assertJsonPath('record.installments.0.amount', '350.00')
            ->assertJsonPath('record.installments.1.label', 'Installment 1')
            ->assertJsonPath('record.installments.1.amount', '525.00')
            ->assertJsonPath('record.installments.2.label', 'Installment 2')
            ->assertJsonPath('record.installments.2.amount', '525.00');

        $invoice = Invoice::query()->firstOrFail();

        $this->assertSame($booking->id, $invoice->booking_id);
        $this->assertCount(3, $invoice->installments);
    }

    public function test_invoice_total_includes_selected_equipment(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Deluxe Booth',
            'base_price' => 1000,
            'is_active' => true,
        ]);

        $equipment = Equipment::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Lighting Rig',
            'category' => 'Lighting',
            'serial_number' => 'EQ-LIGHT-1',
            'daily_rate' => 150,
            'maintenance_status' => 'ready',
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Jamie Customer',
            'customer_email' => 'jamie@example.com',
            'customer_phone' => '0400000000',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'Brisbane',
            'travel_distance_km' => 15,
            'travel_fee' => 200,
            'status' => 'pending',
        ]);
        $booking->equipment()->sync([$equipment->id]);

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/admin/bookings/'.$booking->id.'/invoice', [
                'installment_count' => 2,
                'deposit_percentage' => 50,
                'first_due_date' => now()->addWeek()->toDateString(),
                'interval_days' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('record.total_amount', '1350.00')
            ->assertJsonPath('record.installments.0.amount', '675.00')
            ->assertJsonPath('record.installments.1.amount', '675.00');
    }

    public function test_invoice_total_subtracts_selected_discount(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Deluxe Booth',
            'base_price' => 1000,
            'is_active' => true,
        ]);

        $discount = Discount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'SAVE200',
            'name' => 'Flat Saver',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDay()->toDateString(),
            'discount_type' => 'fixed_amount',
            'discount_value' => 200,
        ]);
        $discount->packages()->sync([$package->id]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'discount_id' => $discount->id,
            'discount_amount' => 200,
            'customer_name' => 'Jamie Customer',
            'customer_email' => 'jamie@example.com',
            'customer_phone' => '0400000000',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'Brisbane',
            'travel_distance_km' => 0,
            'travel_fee' => 0,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/admin/bookings/'.$booking->id.'/invoice', [
                'installment_count' => 2,
                'deposit_percentage' => 50,
                'first_due_date' => now()->addWeek()->toDateString(),
                'interval_days' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('record.total_amount', '800.00')
            ->assertJsonPath('record.installments.0.amount', '400.00')
            ->assertJsonPath('record.installments.1.amount', '400.00');
    }

    public function test_customer_can_pay_invoice_installments(): void
    {
        [$tenant] = $this->tenantUser();

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'url' => 'https://checkout.stripe.com/c/pay_customer_installment',
            ], 200),
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Starter Booth',
            'base_price' => 600,
            'is_active' => true,
        ]);
        $tenant->update([
            'stripe_secret' => 'sk_test_tenant_installments',
            'stripe_currency' => 'nzd',
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Taylor Guest',
            'customer_email' => 'taylor@example.com',
            'customer_phone' => '0400000001',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'Gold Coast',
            'status' => 'pending',
        ]);

        $invoice = Invoice::query()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'invoice_number' => 'INV-TEST-0001',
            'token' => 'invoice-token-123',
            'total_amount' => 600,
            'amount_paid' => 0,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $installment = $invoice->installments()->create([
            'sequence' => 1,
            'label' => 'Installment 1',
            'due_date' => now()->addWeek()->toDateString(),
            'amount' => 300,
            'status' => 'pending',
        ]);

        $invoice->installments()->create([
            'sequence' => 2,
            'label' => 'Installment 2',
            'due_date' => now()->addWeeks(5)->toDateString(),
            'amount' => 300,
            'status' => 'pending',
        ]);

        $this->post('http://'.$tenant->slug.'.memoshot.test/invoices/'.$invoice->token.'/installments/'.$installment->id.'/pay')
            ->assertRedirect('https://checkout.stripe.com/c/pay_customer_installment');

        Http::assertSent(function ($request) use ($invoice, $installment): bool {
            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && str_contains((string) $request['success_url'], 'payment=success')
                && str_contains((string) $request['success_url'], 'installment='.$installment->id)
                && str_contains((string) $request['cancel_url'], 'payment=cancel')
                && str_contains((string) $request['cancel_url'], 'installment='.$installment->id)
                && (string) $request['line_items[0][price_data][currency]'] === 'nzd'
                && (string) $request['metadata[invoice_id]'] === (string) $invoice->id
                && (string) $request['metadata[installment_id]'] === (string) $installment->id;
        });

        $this->assertDatabaseHas('invoice_installments', [
            'id' => $installment->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'issued',
            'amount_paid' => '0.00',
        ]);
    }

    public function test_admin_can_send_invoice_email_with_stripe_checkout_link(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'url' => 'https://checkout.stripe.com/c/pay_test_invoice',
            ], 200),
        ]);
        Mail::fake();

        [$tenant, $user] = $this->tenantUser();
        $tenant->update([
            'stripe_secret' => 'sk_test_tenant_invoice_email',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Luxe Booth',
            'base_price' => 900,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Chris Client',
            'customer_email' => 'chris@example.com',
            'customer_phone' => '0400000002',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'Sunshine Coast',
            'status' => 'pending',
        ]);

        $invoice = Invoice::query()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'invoice_number' => 'INV-TEST-0002',
            'token' => 'invoice-token-456',
            'total_amount' => 900,
            'amount_paid' => 0,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $invoice->installments()->create([
            'sequence' => 1,
            'label' => 'Installment 1',
            'due_date' => now()->addWeek()->toDateString(),
            'amount' => 450,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/admin/bookings/'.$booking->id.'/invoice/send')
            ->assertOk()
            ->assertJsonPath('record.public_url', route('invoices.show', $invoice))
            ->assertJsonPath('record.send_url', route('admin.bookings.invoice.send', $booking));

        Mail::assertSent(InvoiceIssuedMail::class, function (InvoiceIssuedMail $mail) use ($user): bool {
            $mail->assertHasTo('chris@example.com');
            $mail->assertHasCc($user->email);

            return $mail->stripeCheckoutUrl === 'https://checkout.stripe.com/c/pay_test_invoice';
        });

        Http::assertSentCount(1);
    }

    public function test_stripe_webhook_marks_installment_paid(): void
    {
        [$tenant] = $this->tenantUser();

        $tenant->update([
            'stripe_webhook_secret' => 'whsec_test_secret',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Studio Booth',
            'base_price' => 800,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Robin Guest',
            'customer_email' => 'robin@example.com',
            'customer_phone' => '0400000003',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'Brisbane',
            'status' => 'pending',
        ]);

        $invoice = Invoice::query()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'invoice_number' => 'INV-TEST-0003',
            'token' => 'invoice-token-789',
            'total_amount' => 800,
            'amount_paid' => 0,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $installment = $invoice->installments()->create([
            'sequence' => 1,
            'label' => 'Installment 1',
            'due_date' => now()->addWeek()->toDateString(),
            'amount' => 400,
            'status' => 'pending',
        ]);

        $invoice->installments()->create([
            'sequence' => 2,
            'label' => 'Installment 2',
            'due_date' => now()->addWeeks(5)->toDateString(),
            'amount' => 400,
            'status' => 'pending',
        ]);

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'payment_status' => 'paid',
                    'metadata' => [
                        'invoice_id' => (string) $invoice->id,
                        'installment_id' => (string) $installment->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

        $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            ],
            $payload,
        )
            ->assertOk();

        $this->assertDatabaseHas('invoice_installments', [
            'id' => $installment->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'partially_paid',
            'amount_paid' => '400.00',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_stripe_webhook_marks_booking_completed_after_full_payment(): void
    {
        [$tenant] = $this->tenantUser();

        $tenant->update([
            'stripe_webhook_secret' => 'whsec_test_secret',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Studio Booth',
            'base_price' => 800,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Robin Guest',
            'customer_email' => 'robin@example.com',
            'customer_phone' => '0400000003',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'Brisbane',
            'status' => 'pending',
        ]);

        $invoice = Invoice::query()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'invoice_number' => 'INV-TEST-0004',
            'token' => 'invoice-token-999',
            'total_amount' => 800,
            'amount_paid' => 0,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $firstInstallment = $invoice->installments()->create([
            'sequence' => 1,
            'label' => 'Deposit',
            'due_date' => now()->addWeek()->toDateString(),
            'amount' => 400,
            'status' => 'paid',
            'paid_at' => now()->subDay(),
        ]);

        $finalInstallment = $invoice->installments()->create([
            'sequence' => 2,
            'label' => 'Installment 1',
            'due_date' => now()->addWeeks(5)->toDateString(),
            'amount' => 400,
            'status' => 'pending',
        ]);

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'payment_status' => 'paid',
                    'metadata' => [
                        'invoice_id' => (string) $invoice->id,
                        'installment_id' => (string) $finalInstallment->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

        $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            ],
            $payload,
        )
            ->assertOk();

        $this->assertDatabaseHas('invoice_installments', [
            'id' => $finalInstallment->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
            'amount_paid' => '800.00',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'completed',
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
