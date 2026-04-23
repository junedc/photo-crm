<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantReferral;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantOnboardingController extends Controller
{
    public function create(Request $request, CurrentTenant $currentTenant): View|RedirectResponse
    {
        if ($currentTenant->has()) {
            return redirect(Auth::check() ? '/dashboard' : '/login');
        }

        return view('onboarding.create-workspace');
    }

    public function store(Request $request, CurrentTenant $currentTenant): RedirectResponse
    {
        if ($currentTenant->has()) {
            abort(404);
        }

        $validated = $request->validate([
            'tenant_name' => ['required', 'string', 'max:255'],
            'tenant_slug' => [
                'required',
                'string',
                'max:63',
                'alpha_dash:ascii',
                Rule::unique(Tenant::class, 'slug'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'ref' => ['nullable', 'string', 'max:32'],
        ]);

        [$tenant, $user] = DB::transaction(function () use ($validated): array {
            $referrer = filled($validated['ref'] ?? null)
                ? Tenant::query()->where('referral_code', Str::upper($validated['ref']))->first()
                : null;

            $tenant = Tenant::query()->create([
                'name' => $validated['tenant_name'],
                'slug' => Str::lower($validated['tenant_slug']),
                'referral_code' => $this->newReferralCode(),
            ]);

            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(48)),
                'current_tenant_id' => $tenant->getKey(),
            ]);

            $user->tenants()->attach($tenant, ['role' => 'owner']);

            if ($referrer instanceof Tenant && $referrer->isNot($tenant)) {
                TenantReferral::query()->create([
                    'referrer_tenant_id' => $referrer->id,
                    'referred_tenant_id' => $tenant->id,
                    'referral_code' => $referrer->referral_code,
                    'referred_workspace_name' => $tenant->name,
                    'referred_owner_email' => $user->email,
                    'status' => TenantReferral::STATUS_REGISTERED,
                ]);
            }

            return [$tenant, $user];
        });

        Auth::login($user);

        return redirect()->away($this->tenantUrl($request, $tenant, '/dashboard'));
    }

    protected function tenantUrl(Request $request, Tenant $tenant, string $path = '/'): string
    {
        $scheme = $request->getScheme();
        $port = $request->getPort();
        $baseDomain = config('app.tenant_base_domain');
        $host = $tenant->slug.'.'.$baseDomain;
        $portSuffix = in_array($port, [80, 443], true) ? '' : ':'.$port;

        return $scheme.'://'.$host.$portSuffix.$path;
    }

    private function newReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Tenant::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
