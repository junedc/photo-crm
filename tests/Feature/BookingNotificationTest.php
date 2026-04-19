<?php

namespace Tests\Feature;

use App\Mail\AdminBookingCreatedMail;
use App\Mail\CustomerBookingCreatedMail;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_creation_sends_admin_and_customer_emails(): void
    {
        Mail::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Acme Studio',
            'slug' => 'acme',
        ]);

        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'current_tenant_id' => $tenant->id,
        ]);

        $owner->tenants()->attach($tenant, ['role' => 'owner']);

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Deluxe Booth',
            'description' => 'Photo booth package',
            'base_price' => 499,
            'is_active' => true,
        ]);

        Equipment::query()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'name' => 'Backdrop Upgrade',
            'category' => 'Backdrop',
            'description' => 'Premium floral backdrop.',
            'daily_rate' => 99,
            'maintenance_status' => 'ready',
        ]);

        $addOn = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'ADD-001',
            'name' => 'Audio Guest Book',
            'description' => 'Recorded guest message station.',
            'quantity' => 1,
            'unit_price' => 129,
            'duration' => '4 hours',
            'category' => 'add-on',
            'maintenance_status' => 'ready',
        ]);

        $response = $this->post('http://'.$tenant->slug.'.memoshot.test/bookings', [
                'package_id' => $package->id,
                'add_on_ids' => [$addOn->id],
                'customer_name' => 'Jane Customer',
                'customer_email' => 'jane@example.com',
                'customer_phone' => '0400000000',
                'event_type' => 'Wedding',
                'event_date' => now()->addDay()->toDateString(),
                'start_time' => '15:00',
                'end_time' => '19:00',
                'total_hours' => '4.00',
                'event_location' => 'Brisbane',
                'notes' => 'Please arrive early.',
            ]);

        $response->assertRedirect('/bookings/create');

        Mail::assertSent(AdminBookingCreatedMail::class, function (AdminBookingCreatedMail $mail) use ($owner): bool {
            $mail->assertHasTo($owner->email);

            if ($mail->addonsPdf !== null) {
                $mail->assertHasAttachedData($mail->addonsPdf->content, $mail->addonsPdf->name, [
                    'mime' => 'application/pdf',
                ]);
            }

            return true;
        });

        Mail::assertSent(CustomerBookingCreatedMail::class, function (CustomerBookingCreatedMail $mail): bool {
            $mail->assertHasTo('jane@example.com');

            if ($mail->addonsPdf !== null) {
                $mail->assertHasAttachedData($mail->addonsPdf->content, $mail->addonsPdf->name, [
                    'mime' => 'application/pdf',
                ]);
            }

            return true;
        });
    }
}
