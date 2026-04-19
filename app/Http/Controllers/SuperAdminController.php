<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SuperAdminLoginCode;
use App\Models\Tenant;
use App\Models\TenantSubscriptionCharge;
use App\Services\Auth\SuperAdminLoginVerificationService;
use App\Support\PlatformSubscriptionCheckoutLinkGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    public function login(Request $request): View|RedirectResponse
    {
        if ($this->isAuthenticated($request)) {
            return redirect()->route('super-admin.index');
        }

        return view('super-admin.login');
    }

    public function sendCode(Request $request, SuperAdminLoginVerificationService $verificationService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($validated['email']);
        $superAdmin = strtolower((string) config('app.super_admin'));

        if ($superAdmin === '' || $email !== $superAdmin) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This email is not allowed to access platform admin.']);
        }

        $verification = $verificationService->issueFor($email);

        $request->session()->put(SuperAdminLoginVerificationService::SESSION_KEY, [
            'email' => $email,
            'verification_id' => $verification->id,
        ]);

        return redirect()->route('super-admin.verify')
            ->with('status', 'A six-digit admin login code is on its way.');
    }

    public function verify(Request $request): View|RedirectResponse
    {
        if ($this->isAuthenticated($request)) {
            return redirect()->route('super-admin.index');
        }

        if (! $request->session()->has(SuperAdminLoginVerificationService::SESSION_KEY)) {
            return redirect()->route('super-admin.login');
        }

        return view('super-admin.verify');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $pendingVerification = $request->session()->get(SuperAdminLoginVerificationService::SESSION_KEY);

        if (! $pendingVerification) {
            return redirect()->route('super-admin.login');
        }

        $verification = SuperAdminLoginCode::query()
            ->whereKey($pendingVerification['verification_id'])
            ->where('email', $pendingVerification['email'])
            ->first();

        if (! $verification || $verification->consumed_at || $verification->expires_at->isPast()) {
            $request->session()->forget(SuperAdminLoginVerificationService::SESSION_KEY);

            return redirect()->route('super-admin.login')
                ->withErrors(['email' => 'Your admin login code expired. Sign in again to get a new one.']);
        }

        if ($verification->attempts >= 5) {
            return back()->withErrors(['code' => 'Too many attempts. Request a new admin login code.']);
        }

        if (! Hash::check($validated['code'], $verification->code_hash)) {
            $verification->increment('attempts');

            return back()->withErrors(['code' => 'That code is not valid.']);
        }

        $verification->forceFill([
            'consumed_at' => now(),
        ])->save();

        $request->session()->forget(SuperAdminLoginVerificationService::SESSION_KEY);
        $request->session()->put(SuperAdminLoginVerificationService::AUTH_KEY, true);
        $request->session()->put(SuperAdminLoginVerificationService::EMAIL_KEY, $verification->email);
        $request->session()->regenerate();

        return redirect()->route('super-admin.index');
    }

    public function resend(Request $request, SuperAdminLoginVerificationService $verificationService): RedirectResponse
    {
        $pendingVerification = $request->session()->get(SuperAdminLoginVerificationService::SESSION_KEY);

        if (! $pendingVerification) {
            return redirect()->route('super-admin.login');
        }

        $verification = $verificationService->issueFor($pendingVerification['email']);

        $request->session()->put(SuperAdminLoginVerificationService::SESSION_KEY, [
            'email' => $pendingVerification['email'],
            'verification_id' => $verification->id,
        ]);

        return back()->with('status', 'A new admin login code is on its way.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            SuperAdminLoginVerificationService::SESSION_KEY,
            SuperAdminLoginVerificationService::AUTH_KEY,
            SuperAdminLoginVerificationService::EMAIL_KEY,
        ]);

        return redirect()->route('super-admin.login');
    }

    public function index(): View
    {
        return view('super-admin.index', [
            'tenants' => Tenant::query()
                ->with(['subscription', 'latestSubscriptionCharge'])
                ->withCount('users')
                ->orderBy('name')
                ->get(),
            'subscriptionCharges' => TenantSubscriptionCharge::query()
                ->with('tenant')
                ->latest('period_starts_at')
                ->latest()
                ->limit(30)
                ->get(),
            'unpaidSubscriptionCharges' => TenantSubscriptionCharge::query()
                ->with('tenant')
                ->whereIn('status', [
                    TenantSubscriptionCharge::STATUS_PENDING,
                    TenantSubscriptionCharge::STATUS_FAILED,
                ])
                ->latest('period_starts_at')
                ->latest()
                ->get(),
            'subscriptions' => Subscription::query()
                ->withCount('tenants')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'subscriptionOptions' => Subscription::query()
                ->where('is_active', true)
                ->orderBy('price')
                ->orderBy('name')
                ->get(),
            'billingPeriods' => Subscription::billingPeriods(),
            'validityUnits' => Subscription::validityUnits(),
            'platformCurrency' => strtoupper((string) config('services.platform_stripe.currency', 'USD')),
            'baseDomain' => config('app.tenant_base_domain'),
        ]);
    }

    public function storeSubscription(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'billing_period' => ['required', Rule::in(array_keys(Subscription::billingPeriods()))],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'validity_count' => ['nullable', 'integer', 'min:1', 'max:999'],
            'validity_unit' => ['nullable', Rule::in(array_keys(Subscription::validityUnits()))],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Subscription::query()->create($this->normalizeSubscriptionData($validated));

        return back()->with('status', 'Subscription plan created.');
    }

    public function updateSubscription(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'billing_period' => ['required', Rule::in(array_keys(Subscription::billingPeriods()))],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'validity_count' => ['nullable', 'integer', 'min:1', 'max:999'],
            'validity_unit' => ['nullable', Rule::in(array_keys(Subscription::validityUnits()))],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $subscription->update($this->normalizeSubscriptionData($validated));

        return back()->with('status', "Subscription plan updated: {$subscription->name}.");
    }

    public function updateTenantSubscription(
        Request $request,
        Tenant $tenant,
        PlatformSubscriptionCheckoutLinkGenerator $checkoutLinkGenerator,
    ): RedirectResponse {
        $validated = $request->validate([
            'subscription_id' => ['nullable', Rule::exists('subscriptions', 'id')->where('is_active', true)],
        ]);

        $subscription = filled($validated['subscription_id'] ?? null)
            ? Subscription::query()->find($validated['subscription_id'])
            : null;

        $tenant->forceFill([
            'subscription_id' => $subscription?->id,
        ])->save();

        if ($subscription?->billing_period === Subscription::BILLING_FREE_FOR_LIFE) {
            $charge = $checkoutLinkGenerator->chargeForCurrentPeriod($tenant, $subscription);
            $charge->update([
                'status' => TenantSubscriptionCharge::STATUS_WAIVED,
                'paid_at' => now(),
                'notes' => 'Free for life assigned by platform admin.',
            ]);
        }

        return back()->with('status', $subscription
            ? "{$tenant->name} has been assigned to {$subscription->name}."
            : "{$tenant->name} no longer has a selected subscription.");
    }

    public function updateTenantAccess(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'subscription_enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['subscription_enabled'];

        $tenant->forceFill([
            'subscription_enabled' => $enabled,
            'subscription_disabled_at' => $enabled ? null : now(),
        ])->save();

        return back()->with('status', $enabled ? "{$tenant->name} has been enabled." : "{$tenant->name} has been disabled.");
    }

    private function isAuthenticated(Request $request): bool
    {
        return (bool) $request->session()->get(SuperAdminLoginVerificationService::AUTH_KEY, false)
            && strtolower((string) $request->session()->get(SuperAdminLoginVerificationService::EMAIL_KEY)) === strtolower((string) config('app.super_admin'));
    }

    private function normalizeSubscriptionData(array $data): array
    {
        $data['currency'] = strtoupper($data['currency']);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        if ($data['billing_period'] === Subscription::BILLING_FREE_FOR_LIFE) {
            $data['price'] = 0;
            $data['validity_count'] = null;
            $data['validity_unit'] = null;
        }

        if (blank($data['validity_count'] ?? null)) {
            $data['validity_count'] = null;
            $data['validity_unit'] = null;
        }

        return $data;
    }
}
