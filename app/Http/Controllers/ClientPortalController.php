<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ClientPortalCode;
use App\Services\ClientPortalService;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    public function login(Request $request, CurrentTenant $currentTenant, ClientPortalService $clientPortalService): View|RedirectResponse
    {
        $tenant = $currentTenant->get();

        if (! $tenant) {
            abort(404);
        }

        if ($request->session()->has(ClientPortalService::AUTH_SESSION_KEY)) {
            return redirect()->route('client.portal.index');
        }

        $access = $clientPortalService->resolveGrantedAccess(
            $tenant,
            token: $request->query('access'),
        );

        return view('client-portal.login', [
            'tenant' => $tenant,
            'prefillEmail' => $access?->customer_email ?? old('email'),
            'accessToken' => $access?->invite_token ?? (string) $request->query('access', ''),
        ]);
    }

    public function sendCode(Request $request, CurrentTenant $currentTenant, ClientPortalService $clientPortalService): RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant, 404);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'access' => ['nullable', 'uuid'],
        ]);

        $access = $clientPortalService->resolveGrantedAccess(
            $tenant,
            email: strtolower($data['email']),
            token: $data['access'] ?? null,
        );

        if (! $access) {
            return back()
                ->withInput($request->only('email', 'access'))
                ->withErrors(['email' => 'This email does not have client portal access yet.']);
        }

        $verification = $clientPortalService->issueCode($access);

        $request->session()->put(ClientPortalService::ACCESS_SESSION_KEY, [
            'access_id' => $access->id,
            'verification_id' => $verification->id,
            'email' => $access->customer_email,
        ]);

        return redirect()
            ->route('client.portal.verify')
            ->with('status', 'We emailed a six-digit portal code to you.');
    }

    public function verify(Request $request, CurrentTenant $currentTenant): View|RedirectResponse
    {
        abort_unless($currentTenant->get(), 404);

        if (! $request->session()->has(ClientPortalService::ACCESS_SESSION_KEY)) {
            return redirect()->route('client.portal.login');
        }

        return view('client-portal.verify', [
            'tenant' => $currentTenant->get(),
        ]);
    }

    public function confirm(Request $request, CurrentTenant $currentTenant, ClientPortalService $clientPortalService): RedirectResponse
    {
        abort_unless($currentTenant->get(), 404);

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $pendingAccess = $request->session()->get(ClientPortalService::ACCESS_SESSION_KEY);

        if (! $pendingAccess) {
            return redirect()->route('client.portal.login');
        }

        $verification = ClientPortalCode::query()
            ->with('access')
            ->whereKey($pendingAccess['verification_id'])
            ->where('client_portal_access_id', $pendingAccess['access_id'])
            ->where('email', $pendingAccess['email'])
            ->first();

        if (! $verification || $verification->consumed_at || $verification->expires_at->isPast()) {
            $request->session()->forget(ClientPortalService::ACCESS_SESSION_KEY);

            return redirect()->route('client.portal.login')
                ->withErrors(['email' => 'Your portal code expired. Request a new one to continue.']);
        }

        if ($verification->attempts >= 5) {
            return back()->withErrors(['code' => 'Too many attempts. Request a new portal code.']);
        }

        if (! $clientPortalService->codeMatches($verification, $data['code'])) {
            $verification->increment('attempts');

            return back()->withErrors(['code' => 'That code is not valid.']);
        }

        $verification->forceFill([
            'consumed_at' => now(),
        ])->save();

        $verification->access->forceFill([
            'last_verified_at' => now(),
        ])->save();

        $request->session()->forget(ClientPortalService::ACCESS_SESSION_KEY);
        $request->session()->put(ClientPortalService::AUTH_SESSION_KEY, [
            'access_id' => $verification->access->id,
            'email' => $verification->access->customer_email,
        ]);
        $request->session()->regenerate();

        return redirect()->route('client.portal.index');
    }

    public function resend(Request $request, CurrentTenant $currentTenant, ClientPortalService $clientPortalService): RedirectResponse
    {
        abort_unless($currentTenant->get(), 404);

        $pendingAccess = $request->session()->get(ClientPortalService::ACCESS_SESSION_KEY);

        if (! $pendingAccess) {
            return redirect()->route('client.portal.login');
        }

        $access = $clientPortalService->resolveGrantedAccess($currentTenant->get(), email: $pendingAccess['email']);
        abort_unless($access, 404);

        $verification = $clientPortalService->issueCode($access);

        $request->session()->put(ClientPortalService::ACCESS_SESSION_KEY, [
            'access_id' => $access->id,
            'verification_id' => $verification->id,
            'email' => $access->customer_email,
        ]);

        return back()->with('status', 'A fresh portal code is on its way.');
    }

    public function index(Request $request, CurrentTenant $currentTenant): View|RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant, 404);

        $auth = $request->session()->get(ClientPortalService::AUTH_SESSION_KEY);

        if (! $auth) {
            return redirect()->route('client.portal.login');
        }

        $email = strtolower((string) ($auth['email'] ?? ''));

        $bookings = Booking::query()
            ->with(['package', 'invoice.installments', 'addOns'])
            ->where('customer_email', $email)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        $upcomingBookings = $bookings
            ->filter(fn (Booking $booking) => $booking->event_date === null || $booking->event_date->isToday() || $booking->event_date->isFuture())
            ->values();

        $pastBookings = $bookings
            ->filter(fn (Booking $booking) => $booking->event_date !== null && $booking->event_date->isPast() && ! $booking->event_date->isToday())
            ->values();

        return view('client-portal.index', [
            'tenant' => $tenant,
            'customerEmail' => $email,
            'customerName' => $bookings->first()?->customer_name,
            'upcomingBookings' => $upcomingBookings,
            'pastBookings' => $pastBookings,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            ClientPortalService::ACCESS_SESSION_KEY,
            ClientPortalService::AUTH_SESSION_KEY,
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client.portal.login');
    }
}
