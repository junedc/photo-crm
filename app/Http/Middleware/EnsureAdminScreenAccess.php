<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\AdminAccess;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminScreenAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = app(CurrentTenant::class)->get();
        $screen = AdminAccess::screenForRequest($request);

        if ($user === null || $tenant === null || $screen === null) {
            return $next($request);
        }

        $membership = $user->tenants()
            ->whereKey($tenant->getKey())
            ->first()?->pivot;

        if ($membership === null || $membership->role === 'owner' || $membership->role_id === null) {
            return $next($request);
        }

        $role = Role::query()->find($membership->role_id);
        $screens = $role?->screen_access ?? [];

        if (in_array($screen, $screens, true)) {
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN, 'You do not have access to this screen.');
    }
}
