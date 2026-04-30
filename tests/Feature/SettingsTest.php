<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_settings_page(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/settings')
            ->assertOk()
            ->assertSee('data-page="settings"', false);
    }

    public function test_admin_can_update_workspace_settings_and_logo(): void
    {
        Storage::fake('public');

        [$tenant, $user] = $this->tenantUser();

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/settings/workspace', [
                'name' => 'MemoShot Brisbane',
                'abn' => '12 345 678 901',
                'contact_email' => 'hello@memoshot.test',
                'contact_phone' => '0400123456',
                'address' => '123 Queen Street, Brisbane',
                'timezone' => 'Australia/Brisbane',
                'invoice_deposit_percentage' => 35,
                'travel_free_kilometers' => 20,
                'travel_fee_per_kilometer' => 4.5,
                'packages_api_key' => 'packages-key-456',
                'stripe_secret' => 'sk_test_workspace',
                'stripe_webhook_secret' => 'whsec_workspace',
                'stripe_currency' => 'nzd',
                'quote_prefix' => 'QTBNE',
                'invoice_prefix' => 'INVBNE',
                'customer_package_discount_percentage' => 12.5,
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Workspace settings updated.')
            ->assertJsonPath('record.name', 'MemoShot Brisbane')
            ->assertJsonPath('record.abn', '12 345 678 901')
            ->assertJsonPath('record.contact_email', 'hello@memoshot.test')
            ->assertJsonPath('record.timezone', 'Australia/Brisbane')
            ->assertJsonPath('record.invoice_deposit_percentage', '35.00')
            ->assertJsonPath('record.travel_free_kilometers', '20.00')
            ->assertJsonPath('record.travel_fee_per_kilometer', '4.50')
            ->assertJsonPath('record.packages_api_key', 'packages-key-456')
            ->assertJsonPath('record.stripe_secret', '')
            ->assertJsonPath('record.stripe_webhook_secret', '')
            ->assertJsonPath('record.stripe_secret_configured', true)
            ->assertJsonPath('record.stripe_webhook_secret_configured', true)
            ->assertJsonPath('record.stripe_currency', 'nzd')
            ->assertJsonPath('record.quote_prefix', 'QTBNE')
            ->assertJsonPath('record.invoice_prefix', 'INVBNE')
            ->assertJsonPath('record.customer_package_discount_percentage', '12.50');

        $tenant->refresh();

        $this->assertSame('MemoShot Brisbane', $tenant->name);
        $this->assertSame('12 345 678 901', $tenant->abn);
        $this->assertSame('hello@memoshot.test', $tenant->contact_email);
        $this->assertSame('0400123456', $tenant->contact_phone);
        $this->assertSame('123 Queen Street, Brisbane', $tenant->address);
        $this->assertSame('Australia/Brisbane', $tenant->timezone);
        $this->assertSame('35.00', number_format((float) $tenant->invoice_deposit_percentage, 2, '.', ''));
        $this->assertSame('20.00', number_format((float) $tenant->travel_free_kilometers, 2, '.', ''));
        $this->assertSame('4.50', number_format((float) $tenant->travel_fee_per_kilometer, 2, '.', ''));
        $this->assertSame('packages-key-456', $tenant->packages_api_key);
        $this->assertSame('sk_test_workspace', $tenant->stripe_secret);
        $this->assertSame('whsec_workspace', $tenant->stripe_webhook_secret);
        $this->assertSame('nzd', $tenant->stripe_currency);
        $this->assertSame('QTBNE', $tenant->quote_prefix);
        $this->assertSame('INVBNE', $tenant->invoice_prefix);
        $this->assertSame('12.50', number_format((float) $tenant->customer_package_discount_percentage, 2, '.', ''));
        $this->assertNotNull($tenant->logo_path);
        Storage::disk('public')->assertExists($tenant->logo_path);
    }

    public function test_admin_can_update_account_profile(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/settings/account', [
                'name' => 'Updated Owner',
                'email' => 'updated-owner@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Account settings updated.')
            ->assertJsonPath('record.email', 'updated-owner@example.com');

        $user->refresh();

        $this->assertSame('Updated Owner', $user->name);
        $this->assertSame('updated-owner@example.com', $user->email);
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
            'password' => 'password',
        ]);

        $user->tenants()->attach($tenant, ['role' => 'owner']);

        return [$tenant, $user];
    }
}
