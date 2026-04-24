<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminAccess;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserRoleController extends Controller
{
    public function users(CurrentTenant $currentTenant): View
    {
        return $this->renderAdminPage('users', [
            'tenant' => $this->serializeTenant($currentTenant->get()),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('users.store'),
                'users' => route('users.index'),
            ],
            'users' => $this->tenantUsers($currentTenant)->map(fn (User $user) => $this->serializeUser($user))->values(),
            'roles' => $this->tenantRoles()->map(fn (Role $role) => $this->serializeRole($role))->values(),
        ]);
    }

    public function roles(CurrentTenant $currentTenant): View
    {
        return $this->renderAdminPage('roles', [
            'tenant' => $this->serializeTenant($currentTenant->get()),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('roles.store'),
                'roles' => route('roles.index'),
            ],
            'roles' => $this->tenantRoles()->map(fn (Role $role) => $this->serializeRole($role))->values(),
            'screens' => AdminAccess::screens(),
        ]);
    }

    public function access(CurrentTenant $currentTenant): View
    {
        return $this->renderAdminPage('access', [
            'tenant' => $this->serializeTenant($currentTenant->get()),
            'routes' => [
                ...$this->baseRoutes(),
                'update' => route('access.update'),
                'access' => route('access.index'),
                'guestStore' => route('access.guests.store'),
            ],
            'users' => $this->tenantUsers($currentTenant)->map(fn (User $user) => $this->serializeUser($user))->values(),
            'guestUsers' => $this->tenantGuestUsers($currentTenant)->map(fn (User $user) => $this->serializeUser($user))->values(),
            'roles' => $this->tenantRoles()->map(fn (Role $role) => $this->serializeRole($role))->values(),
            'screens' => AdminAccess::screens(),
        ]);
    }

    public function storeUser(Request $request, CurrentTenant $currentTenant): RedirectResponse|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $data = $this->validateUser($request);

        $user = DB::transaction(function () use ($data, $tenant): User {
            $user = User::query()->firstOrCreate(
                ['email' => Str::lower($data['email'])],
                [
                    'name' => $data['name'],
                    'password' => Hash::make(Str::random(48)),
                    'current_tenant_id' => $tenant->id,
                ],
            );

            $user->update([
                'name' => $data['name'],
                'current_tenant_id' => $user->current_tenant_id ?: $tenant->id,
            ]);

            $user->tenants()->syncWithoutDetaching([
                $tenant->id => [
                    'role' => 'member',
                    'role_id' => $data['role_id'] ?? null,
                ],
            ]);

            return $user->fresh();
        });

        $user->load('tenants');

        return $this->savedResponse($request, 'User added.', $this->serializeUser($user), route('users.index'));
    }

    public function updateUser(Request $request, CurrentTenant $currentTenant, User $user): RedirectResponse|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $this->assertTenantUser($tenant, $user);
        $data = $this->validateUser($request, $user);

        $user->update([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
        ]);
        $user->tenants()->updateExistingPivot($tenant->id, ['role_id' => $data['role_id'] ?? null]);
        $user->refresh()->load('tenants');

        return $this->savedResponse($request, 'User updated.', $this->serializeUser($user), route('users.index'));
    }

    public function destroyUser(Request $request, CurrentTenant $currentTenant, User $user): RedirectResponse|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $this->assertTenantUser($tenant, $user);

        if ($request->user()?->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot remove your own access.']);
        }

        $user->tenants()->detach($tenant->id);

        return $this->deletedResponse($request, 'User removed.', route('users.index'));
    }

    public function storeRole(Request $request): RedirectResponse|JsonResponse
    {
        $role = Role::query()->create($this->validateRole($request));

        return $this->savedResponse($request, 'Role added.', $this->serializeRole($role), route('roles.index'));
    }

    public function updateRole(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $role->update($this->validateRole($request, $role));

        return $this->savedResponse($request, 'Role updated.', $this->serializeRole($role->fresh()), route('roles.index'));
    }

    public function destroyRole(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        if (DB::table('tenant_user')->where('role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['role' => 'This role is assigned to users and cannot be deleted.']);
        }

        $role->delete();

        return $this->deletedResponse($request, 'Role deleted.', route('roles.index'));
    }

    public function updateAccess(Request $request, CurrentTenant $currentTenant): JsonResponse|RedirectResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $screenKeys = AdminAccess::screenKeys();

        $data = $request->validate([
            'users' => ['nullable', 'array'],
            'users.*.id' => ['required', 'integer', Rule::exists('users', 'id')],
            'users.*.role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id))],
            'roles' => ['nullable', 'array'],
            'roles.*.id' => ['required', 'integer', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id))],
            'roles.*.screen_access' => ['nullable', 'array'],
            'roles.*.screen_access.*' => ['string', Rule::in($screenKeys)],
        ]);

        DB::transaction(function () use ($data, $tenant, $screenKeys): void {
            foreach ($data['users'] ?? [] as $entry) {
                $user = User::query()->find($entry['id']);

                if ($user !== null && $user->tenants()->whereKey($tenant->id)->exists()) {
                    $user->tenants()->updateExistingPivot($tenant->id, ['role_id' => $entry['role_id'] ?? null]);
                }
            }

            foreach ($data['roles'] ?? [] as $entry) {
                Role::query()
                    ->whereKey($entry['id'])
                    ->update(['screen_access' => array_values(array_intersect($entry['screen_access'] ?? [], $screenKeys))]);
            }
        });

        return $this->savedResponse($request, 'Access updated.', [
            'users' => $this->tenantUsers($currentTenant)->map(fn (User $user) => $this->serializeUser($user))->values(),
            'guestUsers' => $this->tenantGuestUsers($currentTenant)->map(fn (User $user) => $this->serializeUser($user))->values(),
            'roles' => $this->tenantRoles()->map(fn (Role $role) => $this->serializeRole($role))->values(),
        ], route('access.index'));
    }

    public function storeGuestAccess(Request $request, CurrentTenant $currentTenant): RedirectResponse|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id))],
        ]);

        $email = Str::lower($data['email']);

        $user = DB::transaction(function () use ($tenant, $email, $data): User {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => Str::headline(Str::before($email, '@')),
                    'password' => Hash::make(Str::random(48)),
                    'current_tenant_id' => $tenant->id,
                ],
            );

            $existingMembership = $user->tenants()->whereKey($tenant->id)->first()?->pivot;

            if ($existingMembership && $existingMembership->role !== 'guest') {
                throw ValidationException::withMessages([
                    'email' => 'This email already has staff access in the workspace.',
                ]);
            }

            $user->tenants()->syncWithoutDetaching([
                $tenant->id => [
                    'role' => 'guest',
                    'role_id' => $data['role_id'] ?? null,
                ],
            ]);

            return $user->fresh()->load('tenants');
        });

        return $this->savedResponse($request, 'Guest access granted.', $this->serializeUser($user), route('access.index'));
    }

    public function destroyGuestAccess(Request $request, CurrentTenant $currentTenant, User $user): RedirectResponse|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $membership = $user->tenants()->whereKey($tenant->id)->first()?->pivot;

        abort_unless($membership?->role === 'guest', 404);

        $user->tenants()->detach($tenant->id);

        return $this->deletedResponse($request, 'Guest access removed.', route('access.index'));
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => $user === null
                ? ['required', 'email', 'max:255']
                : ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ]);
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId))->ignore($role)],
            'description' => ['nullable', 'string'],
            'screen_access' => ['nullable', 'array'],
            'screen_access.*' => ['string', Rule::in(AdminAccess::screenKeys())],
        ]);

        $data['screen_access'] = array_values($data['screen_access'] ?? []);

        return $data;
    }

    private function tenantUsers(CurrentTenant $currentTenant)
    {
        $tenant = $this->requireTenant($currentTenant);

        return $tenant->users()->with('tenants')->wherePivot('role', '!=', 'guest')->orderBy('name')->get();
    }

    private function tenantGuestUsers(CurrentTenant $currentTenant)
    {
        $tenant = $this->requireTenant($currentTenant);

        return $tenant->users()->with('tenants')->wherePivot('role', 'guest')->orderBy('email')->get();
    }

    private function tenantRoles()
    {
        return Role::query()->withCount('users')->orderBy('name')->get();
    }

    private function assertTenantUser(Tenant $tenant, User $user): void
    {
        abort_unless($user->tenants()->whereKey($tenant->id)->exists(), 404);
    }

    private function requireTenant(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }

    private function serializeUser(User $user): array
    {
        $tenantId = app(CurrentTenant::class)->id();
        $membership = $user->tenants->firstWhere('id', $tenantId)?->pivot;
        $role = $membership?->role_id ? Role::query()->find($membership->role_id) : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'membership_role' => $membership?->role ?? 'member',
            'role_id' => $membership?->role_id,
            'role_name' => $role?->name ?? ($membership?->role === 'owner' ? 'Owner' : ($membership?->role === 'guest' ? 'Guest' : 'Unassigned')),
            'created_at' => $user->created_at?->format('d M Y'),
            'update_url' => route('users.update', $user),
            'delete_url' => route('users.destroy', $user),
            'guest_delete_url' => route('access.guests.destroy', $user),
        ];
    }

    private function serializeRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'screen_access' => $role->screen_access ?? [],
            'users_count' => $role->users_count ?? DB::table('tenant_user')->where('role_id', $role->id)->count(),
            'created_at' => $role->created_at?->format('d M Y'),
            'update_url' => route('roles.update', $role),
            'delete_url' => route('roles.destroy', $role),
        ];
    }

    private function serializeTenant(?Tenant $tenant): ?array
    {
        return $tenant ? [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path ? '/storage/'.ltrim($tenant->logo_path, '/') : null,
            'theme' => $tenant->theme ?: 'dark',
            'google_maps_api_key' => env('VITE_GOOGLE_MAPS_API_KEY', ''),
        ] : null;
    }

    private function baseRoutes(): array
    {
        return [
            'login' => route('login'),
            'dashboard' => route('dashboard'),
            'calendar' => route('admin.calendar.index'),
            'packages' => route('packages.index'),
            'equipment' => route('equipment.index'),
            'addons' => route('addons.index'),
            'discounts' => route('discounts.index'),
            'bookings' => route('admin.bookings.index'),
            'quotes' => route('admin.quotes.index'),
            'invoices' => route('admin.invoices.index'),
            'leads' => route('leads.index'),
            'customers' => route('customers.index'),
            'campaigns' => route('campaigns.index'),
            'tasks' => route('tasks.index'),
            'users' => route('users.index'),
            'roles' => route('roles.index'),
            'access' => route('access.index'),
            'settings' => route('settings.index'),
            'logout' => route('logout'),
        ];
    }

    private function renderAdminPage(string $page, array $props): View
    {
        return view('admin.app', compact('page', 'props'));
    }

    private function savedResponse(Request $request, string $message, array $record, string $redirectTo): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'record' => $record]);
        }

        return redirect($redirectTo)->with('status', $message);
    }

    private function deletedResponse(Request $request, string $message, string $redirectTo): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect($redirectTo)->with('status', $message);
    }
}
