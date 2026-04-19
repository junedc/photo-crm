<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginCode;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\LoginVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginVerificationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(LoginVerificationService::SESSION_KEY)) {
            return redirect()->route('login');
        }

        return view('auth.verify-login-code');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $pendingVerification = $request->session()->get(LoginVerificationService::SESSION_KEY);

        if (! $pendingVerification) {
            return redirect()->route('login');
        }

        $verification = LoginCode::query()
            ->with(['tenant', 'user.tenants'])
            ->whereKey($pendingVerification['verification_id'])
            ->where('tenant_id', $pendingVerification['tenant_id'])
            ->where('user_id', $pendingVerification['user_id'])
            ->first();

        if (! $verification || $verification->consumed_at || $verification->expires_at->isPast()) {
            $request->session()->forget(LoginVerificationService::SESSION_KEY);

            return redirect()->route('login')
                ->withErrors([
                    'email' => 'Your login code expired. Sign in again to get a new one.',
                ]);
        }

        if ($verification->attempts >= 5) {
            return back()->withErrors([
                'code' => 'Too many attempts. Request a new login code.',
            ]);
        }

        if (! Hash::check($validated['code'], $verification->code_hash)) {
            $verification->increment('attempts');

            return back()->withErrors([
                'code' => 'That code is not valid.',
            ]);
        }

        if (! $verification->user->tenants->contains($verification->tenant)) {
            $request->session()->forget(LoginVerificationService::SESSION_KEY);

            return redirect()->route('login')
                ->withErrors([
                    'email' => 'Your account does not have access to this tenant.',
                ]);
        }

        $verification->forceFill([
            'consumed_at' => now(),
        ])->save();

        $request->session()->forget(LoginVerificationService::SESSION_KEY);

        Auth::login($verification->user, (bool) ($pendingVerification['remember'] ?? false));
        $request->session()->regenerate();

        $verification->user->forceFill([
            'current_tenant_id' => $verification->tenant_id,
        ])->save();

        return redirect()->intended('/dashboard');
    }

    public function resend(Request $request, LoginVerificationService $verificationService): RedirectResponse
    {
        $pendingVerification = $request->session()->get(LoginVerificationService::SESSION_KEY);

        if (! $pendingVerification) {
            return redirect()->route('login');
        }

        $user = User::query()->findOrFail($pendingVerification['user_id']);
        $tenant = Tenant::query()->findOrFail($pendingVerification['tenant_id']);
        $verification = $verificationService->issueFor($user, $tenant, $user->email);

        $request->session()->put(LoginVerificationService::SESSION_KEY, [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'verification_id' => $verification->id,
            'remember' => (bool) ($pendingVerification['remember'] ?? false),
        ]);

        return back()->with('status', 'A new login code is on its way.');
    }
}
