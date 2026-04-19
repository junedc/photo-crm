<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_domain_can_create_a_workspace_and_first_owner(): void
    {
        $response = $this->post('http://memoshot.test/workspaces', [
            'tenant_name' => 'Acme Studio',
            'tenant_slug' => 'acme',
            'name' => 'Taylor Owner',
            'email' => 'owner@example.com',
        ]);

        $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();

        $response->assertRedirect('http://acme.memoshot.test/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);
    }

    public function test_root_on_a_tenant_subdomain_redirects_into_that_tenant_auth_flow(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'acme',
        ]);

        $this->get('http://acme.memoshot.test/')
            ->assertRedirect(route('login'));

        $this->assertSame('acme', $tenant->slug);
    }
}
