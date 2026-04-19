<?php

namespace Tests\Feature;

use App\Mail\LoginVerificationCodeMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TenantAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_owner_can_register_from_a_tenant_subdomain(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->post('http://'.$tenant->slug.'.memoshot.test/register', [
            'name' => 'Taylor Owner',
            'email' => 'owner@example.com',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'current_tenant_id' => $tenant->id,
        ]);
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);
    }

    public function test_registration_is_closed_once_a_tenant_has_users(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->attach($tenant, ['role' => 'owner']);

        $this->from('http://'.$tenant->slug.'.memoshot.test/register')
            ->post('http://'.$tenant->slug.'.memoshot.test/register', [
                'name' => 'Blocked User',
                'email' => 'blocked@example.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }

    public function test_tenant_member_can_log_in_from_their_subdomain(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'password',
        ]);
        $user->tenants()->attach($tenant, ['role' => 'member']);

        $this->post('http://'.$tenant->slug.'.memoshot.test/login', [
            'email' => 'member@example.com',
        ])
            ->assertRedirect(route('verification.notice', absolute: false));

        $code = null;
        Mail::assertSent(
            LoginVerificationCodeMail::class,
            function (LoginVerificationCodeMail $mail) use (&$code, $user): bool {
                $code = $mail->code;

                return $mail->hasTo($user->email);
            }
        );

        $response = $this->post('http://'.$tenant->slug.'.memoshot.test/verify-login', [
            'code' => $code,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'current_tenant_id' => $tenant->id,
        ]);
    }

    public function test_user_cannot_log_in_to_a_tenant_they_do_not_belong_to(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'email' => 'outsider@example.com',
            'password' => 'password',
        ]);

        $this->from('http://'.$tenant->slug.'.memoshot.test/login')
            ->post('http://'.$tenant->slug.'.memoshot.test/login', [
                'email' => 'outsider@example.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        Mail::assertNothingSent();
    }
}
