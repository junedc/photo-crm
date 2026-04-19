<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_leads_workspace(): void
    {
        [$tenant, $user] = $this->tenantUser();

        Lead::query()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Jamie Prospect',
            'customer_email' => 'jamie@example.com',
            'status' => 'draft',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/leads')
            ->assertOk()
            ->assertSee('data-page="leads"', false)
            ->assertSee('Jamie Prospect');
    }

    public function test_admin_can_create_update_and_delete_a_lead(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $createResponse = $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/leads', [
                'customer_name' => 'Taylor Caller',
                'customer_email' => 'taylor@example.com',
                'customer_phone' => '0400001234',
                'event_date' => now()->addDays(20)->toDateString(),
                'event_location' => 'Brisbane',
                'notes' => 'Asked about weekend pricing.',
                'status' => 'contacted',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Lead added.')
            ->assertJsonPath('record.customer_name', 'Taylor Caller')
            ->assertJsonPath('record.status', 'contacted');

        $leadId = $createResponse->json('record.id');

        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'tenant_id' => $tenant->id,
            'customer_email' => 'taylor@example.com',
            'status' => 'contacted',
        ]);

        $this->actingAs($user)
            ->putJson('http://'.$tenant->slug.'.memoshot.test/leads/'.$leadId, [
                'customer_name' => 'Taylor Caller',
                'customer_email' => 'taylor@example.com',
                'customer_phone' => '0400001234',
                'event_date' => now()->addDays(21)->toDateString(),
                'event_location' => 'Sunshine Coast',
                'notes' => 'Qualified after follow-up.',
                'status' => 'qualified',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Lead updated.')
            ->assertJsonPath('record.event_location', 'Sunshine Coast')
            ->assertJsonPath('record.status', 'qualified');

        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'event_location' => 'Sunshine Coast',
            'status' => 'qualified',
        ]);

        $this->actingAs($user)
            ->deleteJson('http://'.$tenant->slug.'.memoshot.test/leads/'.$leadId)
            ->assertOk()
            ->assertJsonPath('message', 'Lead deleted.');

        $this->assertDatabaseMissing('leads', [
            'id' => $leadId,
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
