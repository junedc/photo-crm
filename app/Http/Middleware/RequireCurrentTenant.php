<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentTenant = app(CurrentTenant::class);

        if (! $currentTenant->has()) {
            abort(Response::HTTP_NOT_FOUND, 'Tenant not found.');
        }

        if (! $currentTenant->get()?->subscription_enabled && ! $this->routeIsAllowedWhileDisabled($request)) {
            abort(Response::HTTP_PAYMENT_REQUIRED, 'This workspace is currently disabled. Please contact support to reactivate your subscription.');
        }

        return $next($request);
    }

    private function routeIsAllowedWhileDisabled(Request $request): bool
    {
        return $request->routeIs(
            'login',
            'login.store',
            'verification.notice',
            'verification.verify',
            'verification.resend',
            'settings.index',
            'settings.workspace.update',
            'settings.subscription.pay',
            'logout',
        );
    }
}
