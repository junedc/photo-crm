<?php

namespace App\Http\Middleware;

use App\Services\Auth\SuperAdminLoginVerificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredEmail = strtolower((string) config('app.super_admin'));
        $sessionEmail = strtolower((string) $request->session()->get(SuperAdminLoginVerificationService::EMAIL_KEY));

        if (
            $configuredEmail !== ''
            && (bool) $request->session()->get(SuperAdminLoginVerificationService::AUTH_KEY, false)
            && $sessionEmail === $configuredEmail
        ) {
            return $next($request);
        }

        return redirect()->route('super-admin.login');
    }
}
