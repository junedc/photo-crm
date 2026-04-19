<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginVerificationService;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(CurrentTenant $currentTenant): View
    {
        return view('auth.login', [
            'tenant' => $currentTenant->get(),
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant, LoginVerificationService $verificationService): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $tenant = $currentTenant->get();
        $email = Str::lower($data['email']);
        $user = User::query()->where('email', $email)->first();

        if ($tenant === null || $user === null || ! $user->tenants()->whereKey($tenant->getKey())->exists()) {
            throw ValidationException::withMessages([
                'email' => 'We could not find an account with access to this tenant.',
            ]);
        }

        $verification = $verificationService->issueFor($user, $tenant, $email);

        $request->session()->put(LoginVerificationService::SESSION_KEY, [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'verification_id' => $verification->id,
            'remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('verification.notice')
            ->with('status', 'We emailed a fresh login code to your inbox.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
