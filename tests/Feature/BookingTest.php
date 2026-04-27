<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Discount;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantStatuses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_booking_page_and_see_active_packages(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Wedding Booth',
            'base_price' => 1500,
            'is_active' => true,
        ]);

        Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Hidden Package',
            'base_price' => 900,
            'is_active' => false,
        ]);

        InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-PUBLIC',
            'name' => 'Public Props',
            'category' => 'add-on',
            'addon_category' => 'Items',
            'is_publicly_displayed' => true,
            'quantity' => 1,
            'unit_price' => 50,
            'maintenance_status' => 'ready',
        ]);

        InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-HIDDEN',
            'name' => 'Private Back Office Add-On',
            'category' => 'add-on',
            'addon_category' => 'Action',
            'is_publicly_displayed' => false,
            'quantity' => 1,
            'unit_price' => 80,
            'maintenance_status' => 'ready',
        ]);

        $this->get('http://'.$tenant->slug.'.memoshot.test/bookings/create')
            ->assertOk()
            ->assertSee('Wedding Booth')
            ->assertDontSee('Hidden Package')
            ->assertSee('Public Props')
            ->assertDontSee('Private Back Office Add-On');
    }

    public function test_customer_can_create_booking_for_active_package(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corporate Booth',
            'base_price' => 1800,
            'is_active' => true,
        ]);

        $packageHourlyPrice = $package->hourlyPrices()->create([
            'tenant_id' => $tenant->id,
            'hours' => 4.50,
            'price' => 2100,
        ]);

        $addOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-001',
            'name' => 'Audio Guest Book',
            'description' => 'Voice message keepsake.',
            'quantity' => 1,
            'unit_price' => 149,
            'duration' => '4 hours',
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $this->post('http://'.$tenant->slug.'.memoshot.test/bookings', [
            'package_id' => $package->id,
            'add_on_ids' => [$addOn->id],
            'customer_name' => 'Jamie Customer',
            'customer_email' => 'jamie@example.com',
            'customer_phone' => '0400000000',
            'event_type' => 'Wedding',
            'event_date' => now()->addWeek()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '18:30',
            'total_hours' => '4.50',
            'package_hourly_price_id' => $packageHourlyPrice->id,
            'event_location' => 'Brisbane Convention Centre',
            'travel_distance_km' => '12.50',
            'travel_fee' => '62.50',
            'notes' => 'Need setup by 4 PM.',
        ])
            ->assertRedirect(route('bookings.create', absolute: false))
            ->assertSessionHas('status', 'Quote request sent successfully.')
            ->assertSessionHasInput('customer_name', 'Jamie Customer')
            ->assertSessionHasInput('customer_email', 'jamie@example.com')
            ->assertSessionHasInput('customer_phone', '0400000000')
            ->assertSessionHasInput('event_date', now()->addWeek()->toDateString())
            ->assertSessionHasInput('start_time', '14:00')
            ->assertSessionHasInput('end_time', '18:30')
            ->assertSessionHasInput('total_hours', '4.50')
            ->assertSessionHasInput('event_location', 'Brisbane Convention Centre')
            ->assertSessionHasInput('notes', 'Need setup by 4 PM.');

        $booking = Booking::query()->firstOrFail();
        $this->assertSame(sprintf('QT-%06d', $booking->id), $booking->quote_number);
        $this->assertSame('Wedding', $booking->event_type);
        $this->assertSame('2100.00', number_format((float) $booking->package_price, 2, '.', ''));
        $this->assertSame('14:00', $booking->start_time);
        $this->assertSame('18:30', $booking->end_time);
        $this->assertSame('4.50', $booking->total_hours);
        $this->assertSame('12.50', number_format((float) $booking->travel_distance_km, 2, '.', ''));
        $this->assertSame('62.50', number_format((float) $booking->travel_fee, 2, '.', ''));
        $this->assertSame([$addOn->id], $booking->addOns()->pluck('inventory_items.id')->all());
    }

    public function test_customer_package_discount_is_applied_to_public_booking_price(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
            'customer_package_discount_percentage' => 10,
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corporate Booth',
            'base_price' => 1800,
            'is_active' => true,
        ]);

        $packageHourlyPrice = $package->hourlyPrices()->create([
            'tenant_id' => $tenant->id,
            'hours' => 4.50,
            'price' => 2100,
        ]);

        $this->post('http://'.$tenant->slug.'.memoshot.test/bookings', [
            'package_id' => $package->id,
            'customer_name' => 'Jamie Customer',
            'customer_email' => 'jamie@example.com',
            'customer_phone' => '0400000000',
            'event_type' => 'Wedding',
            'event_date' => now()->addWeek()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '18:30',
            'total_hours' => '4.50',
            'package_hourly_price_id' => $packageHourlyPrice->id,
            'event_location' => 'Brisbane Convention Centre',
            'travel_distance_km' => '0.00',
            'travel_fee' => '0.00',
        ])->assertRedirect('/bookings/create');

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('1890.00', number_format((float) $booking->package_price, 2, '.', ''));
    }

    public function test_booking_can_store_selected_discount_details(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corporate Booth',
            'base_price' => 1800,
            'is_active' => true,
        ]);

        $discount = Discount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'SAVE20',
            'name' => 'April Promo',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDay()->toDateString(),
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);
        DB::table('discount_package')->insert([
            'discount_id' => $discount->id,
            'package_id' => $package->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'discount_id' => $discount->id,
            'discount_amount' => 360,
            'customer_name' => 'Jamie Customer',
            'customer_email' => 'jamie@example.com',
            'customer_phone' => '0400000000',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'Brisbane Convention Centre',
            'status' => 'pending',
        ]);
        $booking->load('discount');

        $this->assertSame($discount->id, $booking->discount_id);
        $this->assertSame('360.00', number_format((float) $booking->discount_amount, 2, '.', ''));
        $this->assertSame('SAVE20', $booking->discount?->code);
    }

    public function test_customer_can_accept_and_reject_a_quote_from_the_email_links(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Wedding Booth',
            'base_price' => 1500,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Quote Guest',
            'customer_email' => 'quote@example.com',
            'customer_phone' => '0400000011',
            'event_date' => now()->addWeeks(2)->toDateString(),
            'event_location' => 'Gold Coast',
            'status' => 'pending',
        ]);

        $this->get('http://'.$tenant->slug.'.memoshot.test/quotes/'.$booking->quote_token.'/accept')
            ->assertOk()
            ->assertSee('accepted', false);

        $booking->refresh();
        $this->assertSame('accepted', $booking->customer_response_status);
        $this->assertNotNull($booking->customer_responded_at);

        $this->get('http://'.$tenant->slug.'.memoshot.test/quotes/'.$booking->quote_token.'/reject')
            ->assertOk()
            ->assertSee('rejected', false);

        $booking->refresh();
        $this->assertSame('rejected', $booking->customer_response_status);
    }

    public function test_customer_can_book_now_and_pay_the_deposit_with_stripe(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'url' => 'https://checkout.stripe.com/c/book_now_deposit',
            ], 200),
        ]);

        config()->set('services.stripe.secret', 'sk_test_123');
        config()->set('invoicing.deposit_percentage', 30);

        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corporate Booth',
            'base_price' => 1000,
            'is_active' => true,
        ]);

        $packageHourlyPrice = $package->hourlyPrices()->create([
            'tenant_id' => $tenant->id,
            'hours' => 5.00,
            'price' => 1200,
        ]);

        $addOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-002',
            'name' => 'Backdrop Upgrade',
            'description' => 'Premium backdrop wall.',
            'quantity' => 1,
            'unit_price' => 200,
            'duration' => 'Full event',
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $this->post('http://'.$tenant->slug.'.memoshot.test/bookings/book-now', [
            'package_id' => $package->id,
            'add_on_ids' => [$addOn->id],
            'customer_name' => 'Jamie Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '0400000066',
            'event_type' => 'Birthday',
            'event_date' => now()->addWeeks(4)->toDateString(),
            'start_time' => '16:00',
            'end_time' => '21:00',
            'total_hours' => '5.00',
            'package_hourly_price_id' => $packageHourlyPrice->id,
            'event_location' => 'Brisbane City',
            'travel_distance_km' => '10.00',
            'travel_fee' => '200.00',
            'notes' => 'Ready to confirm now.',
            'terms_accepted' => '1',
        ])->assertRedirect('https://checkout.stripe.com/c/book_now_deposit');

        $booking = Booking::query()->firstOrFail();
        $invoice = $booking->invoice()->with('installments')->firstOrFail();

        $this->assertSame('Jamie Buyer', $booking->customer_name);
        $this->assertSame(sprintf('QT-%06d', $booking->id), $booking->quote_number);
        $this->assertSame('Birthday', $booking->event_type);
        $this->assertSame('1200.00', number_format((float) $booking->package_price, 2, '.', ''));
        $this->assertSame('accepted', $booking->customer_response_status);
        $this->assertCount(2, $invoice->installments);
        $this->assertSame('Deposit', $invoice->installments[0]->label);
        $this->assertSame('480.00', number_format((float) $invoice->installments[0]->amount, 2, '.', ''));
        $this->assertDatabaseHas('invoice_installments', [
            'invoice_id' => $invoice->id,
            'sequence' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_customer_can_book_now_even_if_selected_discount_no_longer_applies(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'url' => 'https://checkout.stripe.com/c/book_now_without_discount',
            ], 200),
        ]);

        config()->set('services.stripe.secret', 'sk_test_123');
        config()->set('invoicing.deposit_percentage', 30);

        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corporate Booth',
            'base_price' => 1000,
            'is_active' => true,
        ]);

        $packageHourlyPrice = $package->hourlyPrices()->create([
            'tenant_id' => $tenant->id,
            'hours' => 5.00,
            'price' => 1200,
        ]);

        $discount = Discount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'OLDAPRIL',
            'name' => 'Old April Promo',
            'starts_at' => now()->subMonth()->subDays(2)->toDateString(),
            'ends_at' => now()->subMonth()->toDateString(),
            'discount_type' => 'percentage',
            'discount_value' => 25,
        ]);

        $this->post('http://'.$tenant->slug.'.memoshot.test/bookings/book-now', [
            'package_id' => $package->id,
            'discount_id' => $discount->id,
            'customer_name' => 'Jamie Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '0400000066',
            'event_type' => 'Birthday',
            'event_date' => now()->addWeeks(4)->toDateString(),
            'start_time' => '16:00',
            'end_time' => '21:00',
            'total_hours' => '5.00',
            'package_hourly_price_id' => $packageHourlyPrice->id,
            'event_location' => 'Brisbane City',
            'travel_distance_km' => '10.00',
            'travel_fee' => '200.00',
            'notes' => 'Ready to confirm now.',
            'terms_accepted' => '1',
        ])->assertRedirect('https://checkout.stripe.com/c/book_now_without_discount');

        $booking = Booking::query()->firstOrFail();

        $this->assertNull($booking->discount_id);
        $this->assertSame('0.00', number_format((float) $booking->discount_amount, 2, '.', ''));
    }

    public function test_customer_can_book_now_with_valid_package_discount(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'url' => 'https://checkout.stripe.com/c/book_now_package_discount',
            ], 200),
        ]);

        config()->set('services.stripe.secret', 'sk_test_123');
        config()->set('invoicing.deposit_percentage', 30);

        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corporate Booth',
            'base_price' => 1000,
            'is_active' => true,
        ]);

        $packageHourlyPrice = $package->hourlyPrices()->create([
            'tenant_id' => $tenant->id,
            'hours' => 5.00,
            'price' => 1200,
        ]);

        $discount = Discount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'APRIL2026',
            'name' => 'April 2026 discount',
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->endOfMonth()->toDateString(),
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        $discount->packages()->sync([$package->id]);

        $this->post('http://'.$tenant->slug.'.memoshot.test/bookings/book-now', [
            'package_id' => $package->id,
            'discount_id' => $discount->id,
            'customer_name' => 'Jamie Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '0400000066',
            'event_type' => 'Birthday',
            'event_date' => now()->addWeeks(4)->toDateString(),
            'start_time' => '16:00',
            'end_time' => '21:00',
            'total_hours' => '5.00',
            'package_hourly_price_id' => $packageHourlyPrice->id,
            'event_location' => 'Brisbane City',
            'travel_distance_km' => '10.00',
            'travel_fee' => '200.00',
            'notes' => 'Ready to confirm now.',
            'terms_accepted' => '1',
        ])->assertRedirect('https://checkout.stripe.com/c/book_now_package_discount');

        $booking = Booking::query()->firstOrFail();
        $invoice = $booking->invoice()->with('installments')->firstOrFail();

        $this->assertSame($discount->id, $booking->discount_id);
        $this->assertSame('120.00', number_format((float) $booking->discount_amount, 2, '.', ''));
        $this->assertSame('384.00', number_format((float) $invoice->installments[0]->amount, 2, '.', ''));
    }

    public function test_customer_must_choose_package_timing_option_when_duration_does_not_match_a_package_price(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corporate Booth',
            'base_price' => 1800,
            'is_active' => true,
        ]);

        $package->hourlyPrices()->create([
            'tenant_id' => $tenant->id,
            'hours' => 4.00,
            'price' => 1950,
        ]);

        $this->from('http://'.$tenant->slug.'.memoshot.test/bookings/create')
            ->post('http://'.$tenant->slug.'.memoshot.test/bookings', [
                'package_id' => $package->id,
                'customer_name' => 'Jamie Customer',
                'customer_email' => 'jamie@example.com',
                'customer_phone' => '0400000000',
                'event_type' => 'Wedding',
                'event_date' => now()->addWeek()->toDateString(),
                'start_time' => '14:00',
                'end_time' => '18:30',
                'total_hours' => '4.50',
                'event_location' => 'Brisbane Convention Centre',
            ])
            ->assertSessionHasErrors('package_hourly_price_id');
    }

    public function test_get_quote_uses_matching_package_duration_when_hourly_price_id_is_missing(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corporate Booth',
            'base_price' => 1800,
            'is_active' => true,
        ]);

        $package->hourlyPrices()->create([
            'tenant_id' => $tenant->id,
            'hours' => 4.00,
            'price' => 1950,
        ]);

        $this->post('http://'.$tenant->slug.'.memoshot.test/bookings', [
            'package_id' => $package->id,
            'customer_name' => 'Jamie Customer',
            'customer_email' => 'jamie@example.com',
            'customer_phone' => '0400000000',
            'event_type' => 'Wedding',
            'event_date' => now()->addWeek()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '18:00',
            'total_hours' => '4.00',
            'event_location' => 'Brisbane Convention Centre',
        ])
            ->assertRedirect(route('bookings.create', absolute: false))
            ->assertSessionHas('status', 'Quote request sent successfully.');

        $booking = Booking::query()->firstOrFail();

        $this->assertSame('1950.00', number_format((float) $booking->package_price, 2, '.', ''));
        $this->assertSame('4.00', $booking->total_hours);
    }

    public function test_customer_details_can_be_autosaved_as_a_lead_without_creating_a_booking(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $this->postJson('http://'.$tenant->slug.'.memoshot.test/bookings/autosave-lead', [
            'customer_name' => 'Jamie Prospect',
            'customer_email' => 'prospect@example.com',
            'customer_phone' => '0400000099',
            'event_type' => 'Anniversary',
            'event_date' => now()->addWeeks(2)->toDateString(),
            'event_location' => 'Gold Coast',
            'notes' => 'Interested in a Saturday package.',
        ])
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('lead_id', 1);

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $tenant->id,
            'customer_name' => 'Jamie Prospect',
            'customer_email' => 'prospect@example.com',
            'status' => 'draft',
        ]);

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_submitted_booking_marks_the_autosaved_lead_as_booked(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corporate Booth',
            'base_price' => 1800,
            'is_active' => true,
        ]);

        $lead = Lead::query()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Jamie Prospect',
            'customer_email' => 'prospect@example.com',
            'customer_phone' => '0400000099',
            'event_type' => 'Anniversary',
            'event_date' => now()->addWeeks(2)->toDateString(),
            'event_location' => 'Gold Coast',
            'notes' => 'Interested in a Saturday package.',
            'status' => 'draft',
            'last_activity_at' => now(),
        ]);

        $this->post('http://'.$tenant->slug.'.memoshot.test/bookings', [
            'lead_token' => $lead->token,
            'package_id' => $package->id,
            'customer_name' => 'Jamie Prospect',
            'customer_email' => 'prospect@example.com',
            'customer_phone' => '0400000099',
            'event_type' => 'Anniversary',
            'event_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '12:00',
            'end_time' => '15:30',
            'total_hours' => '3.50',
            'event_location' => 'Gold Coast',
            'notes' => 'Ready to book now.',
        ]);

        $lead->refresh();

        $this->assertSame('booked', $lead->status);
        $this->assertNotNull($lead->booking_id);
        $this->assertDatabaseHas('bookings', [
            'id' => $lead->booking_id,
            'tenant_id' => $tenant->id,
            'customer_email' => 'prospect@example.com',
        ]);
    }

    public function test_customer_cannot_book_inactive_package(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Inactive Booth',
            'base_price' => 750,
            'is_active' => false,
        ]);

        $this->from('http://'.$tenant->slug.'.memoshot.test/bookings/create')
            ->post('http://'.$tenant->slug.'.memoshot.test/bookings', [
                'package_id' => $package->id,
            'customer_name' => 'Blocked Customer',
            'customer_email' => 'blocked@example.com',
            'customer_phone' => '0400000001',
            'event_type' => 'Others',
            'event_date' => now()->addWeek()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '12:00',
                'total_hours' => '2.00',
                'event_location' => 'Hotel Ballroom',
            ])
            ->assertSessionHasErrors('package_id');

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_admin_can_view_bookings_workspace(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Event Booth',
            'base_price' => 1200,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Taylor Guest',
            'customer_email' => 'guest@example.com',
            'customer_phone' => '0400000002',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'City Hall',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/admin/bookings/'.$booking->id)
            ->assertOk()
            ->assertSee('data-page="bookings"', false)
            ->assertSee('Taylor Guest')
            ->assertSee('Event Booth');
    }

    public function test_admin_can_update_booking_status(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'VIP Booth',
            'base_price' => 2000,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Jordan Client',
            'customer_email' => 'jordan@example.com',
            'customer_phone' => '0400000003',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'Riverside Venue',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->put('http://'.$tenant->slug.'.memoshot.test/admin/bookings/'.$booking->id, [
                'status' => 'confirmed',
                'notes' => 'Deposit received.',
            ])
            ->assertRedirect(route('admin.bookings.show', $booking, absolute: false));

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
            'notes' => 'Deposit received.',
        ]);
    }

    public function test_admin_can_accept_quote_on_customer_behalf_from_booking_edit(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Acceptance Booth',
            'base_price' => 1200,
            'is_active' => true,
        ]);
        $bookingStatus = TenantStatuses::ensureWorkspaceRecords($tenant, TenantStatuses::SCOPE_BOOKING)
            ->firstWhere('name', 'pending');
        $quoteStatus = TenantStatuses::ensureWorkspaceRecords($tenant, TenantStatuses::SCOPE_QUOTE_RESPONSE)
            ->firstWhere('name', 'accepted');
        $eventDate = now()->addWeek()->toDateString();

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'customer_name' => 'Riley Client',
            'customer_email' => 'riley@example.com',
            'customer_phone' => '0400000099',
            'event_type' => 'Wedding',
            'event_date' => $eventDate,
            'start_time' => '14:00',
            'end_time' => '18:00',
            'total_hours' => 4,
            'event_location' => 'Garden Venue',
            'booking_status_id' => $bookingStatus->id,
            'status' => 'pending',
            'customer_response_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->putJson('http://'.$tenant->slug.'.memoshot.test/admin/bookings/'.$booking->id, [
                'booking_status_id' => $bookingStatus->id,
                'quote_response_status_id' => $quoteStatus->id,
                'booking_kind' => 'customer',
                'package_id' => $package->id,
                'customer_name' => 'Riley Client',
                'customer_email' => 'riley@example.com',
                'customer_phone' => '0400000099',
                'event_type' => 'Wedding',
                'event_date' => $eventDate,
                'start_time' => '14:00',
                'end_time' => '18:00',
                'total_hours' => 4,
                'event_location' => 'Garden Venue',
            ])
            ->assertOk()
            ->assertJsonPath('record.customer_response_status', 'accepted');

        $booking->refresh();

        $this->assertSame($quoteStatus->id, $booking->quote_response_status_id);
        $this->assertSame('accepted', $booking->customer_response_status);
        $this->assertNotNull($booking->customer_responded_at);
    }

    public function test_admin_can_create_booking_from_dashboard(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Booth',
            'description' => 'Premium open-air booth package.',
            'base_price' => 1750,
            'is_active' => true,
        ]);
        $addOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-777',
            'name' => 'Guestbook Station',
            'description' => 'Printed scrapbook table with pens.',
            'quantity' => 1,
            'unit_price' => 199,
            'duration' => 'Full event',
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/bookings', [
                'package_id' => $package->id,
                'add_on_ids' => [$addOn->id],
                'customer_name' => 'Morgan Admin',
                'customer_email' => 'morgan@example.com',
                'customer_phone' => '0400000004',
                'event_type' => 'Wedding',
                'venue' => 'South Bank',
                'event_date' => now()->addDays(10)->toDateString(),
                'start_time' => '13:00',
                'end_time' => '17:00',
                'total_hours' => '4.00',
                'event_location' => 'South Bank',
                'notes' => 'Booked by admin from dashboard.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Booking created successfully.')
            ->assertJsonPath('record.customer_name', 'Morgan Admin')
            ->assertJsonPath('record.event_type', 'Wedding')
            ->assertJsonPath('record.event_type_label', 'Wedding')
            ->assertJsonPath('record.package_name', 'Admin Booth')
            ->assertJsonPath('record.package.description', 'Premium open-air booth package.')
            ->assertJsonPath('record.event_date_label', now()->addDays(10)->format('d M Y'))
            ->assertJsonPath('record.start_time', '13:00')
            ->assertJsonPath('record.start_time_label', '1:00 PM')
            ->assertJsonPath('record.end_time', '17:00')
            ->assertJsonPath('record.end_time_label', '5:00 PM')
            ->assertJsonPath('record.total_hours', '4.00')
            ->assertJsonPath('record.addons.0.name', 'Guestbook Station')
            ->assertJsonPath('record.addons.0.product_code', 'ADD-777');

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('13:00', $booking->start_time);
        $this->assertSame('17:00', $booking->end_time);
        $this->assertSame('4.00', $booking->total_hours);
        $this->assertSame('pending', $booking->status);
    }

    public function test_admin_can_create_market_stall_booking_with_equipment_and_add_ons(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Market Booth',
            'description' => 'Compact stall package.',
            'base_price' => 800,
            'is_active' => true,
        ]);

        $packageHourlyPrice = $package->hourlyPrices()->create([
            'tenant_id' => $tenant->id,
            'hours' => 6.00,
            'price' => 950,
        ]);

        $equipment = Equipment::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'LED Signage',
            'category' => 'Display',
            'serial_number' => 'EQ-100',
            'daily_rate' => 125,
            'maintenance_status' => 'ready',
        ]);

        $addOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-900',
            'name' => 'Flyer Stand',
            'description' => 'Promotional flyer holder.',
            'quantity' => 1,
            'unit_price' => 45,
            'duration' => 'Full event',
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $response = $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/bookings', [
                'booking_kind' => 'market_stall',
                'entry_name' => 'Saturday Farmers Market',
                'entry_description' => 'Fresh produce showcase stall.',
                'package_id' => $package->id,
                'package_hourly_price_id' => $packageHourlyPrice->id,
                'equipment_ids' => [$equipment->id],
                'add_on_ids' => [$addOn->id],
                'customer_name' => 'Taylor Stall Owner',
                'customer_email' => 'stall@example.com',
                'customer_phone' => '0400000088',
                'event_type' => 'Others',
                'event_date' => now()->addDays(12)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '15:00',
                'total_hours' => '6.00',
                'event_location' => 'South Bank Markets',
                'notes' => 'Please invoice the stall owner directly.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('record.booking_kind', 'market_stall')
            ->assertJsonPath('record.booking_kind_label', 'Stall')
            ->assertJsonPath('record.display_name', 'Saturday Farmers Market')
            ->assertJsonPath('record.entry_description', 'Fresh produce showcase stall.')
            ->assertJsonPath('record.customer_name', 'Taylor Stall Owner')
            ->assertJsonPath('record.equipment.0.name', 'LED Signage')
            ->assertJsonPath('record.addons.0.name', 'Flyer Stand');

        $booking = Booking::query()->firstOrFail();

        $this->assertSame('market_stall', $booking->booking_kind);
        $this->assertSame('Saturday Farmers Market', $booking->entry_name);
        $this->assertSame([$equipment->id], $booking->equipment()->pluck('equipment.id')->all());
        $this->assertSame([$addOn->id], $booking->addOns()->pluck('inventory_items.id')->all());
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

