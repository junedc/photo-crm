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
        if (
            (bool) $request->session()->get(SuperAdminLoginVerificationService::AUTH_KEY, false)
            && SuperAdminLoginVerificationService::isAllowedEmail(
                $request->session()->get(SuperAdminLoginVerificationService::EMAIL_KEY)
            )
        ) {
            return $next($request);
        }

        return redirect()->route('super-admin.login');
    }
}
