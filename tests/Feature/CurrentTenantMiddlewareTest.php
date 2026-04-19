<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CurrentTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/current-tenant', function () {
            return response()->json([
                'tenant_id' => app(\App\Tenancy\CurrentTenant::class)->id(),
            ]);
        });
    }

    public function test_it_resolves_the_current_tenant_from_the_request_subdomain(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
        ]);

        $user->tenants()->attach($tenant, ['role' => 'owner']);

        $this->actingAs($user)
            ->getJson('http://'.$tenant->slug.'.memoshot.test/_test/current-tenant')
            ->assertOk()
            ->assertJson([
                'tenant_id' => $tenant->id,
            ]);
    }

    public function test_it_rejects_access_to_a_tenant_subdomain_the_user_does_not_belong_to(): void
    {
        $allowedTenant = Tenant::factory()->create();
        $blockedTenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'current_tenant_id' => $allowedTenant->id,
        ]);

        $user->tenants()->attach($allowedTenant, ['role' => 'member']);

        $this->actingAs($user)
            ->getJson('http://'.$blockedTenant->slug.'.memoshot.test/_test/current-tenant')
            ->assertForbidden();
    }

    public function test_it_returns_not_found_for_unknown_tenant_subdomains(): void
    {
        $this->getJson('http://missing-tenant.memoshot.test/_test/current-tenant')
            ->assertNotFound();
    }
}
