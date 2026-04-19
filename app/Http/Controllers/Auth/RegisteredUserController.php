<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(CurrentTenant $currentTenant): View
    {
        return view('auth.register', [
            'tenant' => $currentTenant->get(),
            'registrationOpen' => $this->registrationIsOpen($currentTenant),
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): RedirectResponse
    {
        $tenant = $currentTenant->get();

        if ($tenant === null) {
            abort(404, 'Tenant not found.');
        }

        if (! $this->registrationIsOpen($currentTenant)) {
            throw ValidationException::withMessages([
                'email' => 'Registration is closed for this tenant. Please ask an administrator for access.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = DB::transaction(function () use ($tenant, $validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(48)),
                'current_tenant_id' => $tenant->getKey(),
            ]);

            $user->tenants()->attach($tenant, ['role' => 'owner']);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect('/dashboard');
    }

    protected function registrationIsOpen(CurrentTenant $currentTenant): bool
    {
        $tenant = $currentTenant->get();

        if ($tenant === null) {
            return false;
        }

        return ! $tenant->users()->exists();
    }
}
