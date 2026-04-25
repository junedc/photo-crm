<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Closure;
use DateTimeZone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentTenant = app(CurrentTenant::class);
        $currentTenant->clear();
        $defaultTimezone = config('app.timezone', 'UTC');
        Config::set('app.timezone', $defaultTimezone);
        date_default_timezone_set($defaultTimezone);

        $tenant = $this->resolveTenant($request);

        if ($tenant !== null) {
            $currentTenant->set($tenant);
            $timezone = $this->resolveTimezone($tenant->timezone);

            Config::set('app.timezone', $timezone);
            date_default_timezone_set($timezone);
        }

        return $next($request);
    }

    protected function resolveTimezone(?string $timezone): string
    {
        if (! is_string($timezone) || $timezone === '') {
            return config('app.timezone', 'UTC');
        }

        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (\Throwable) {
            return config('app.timezone', 'UTC');
        }
    }

    protected function resolveTenant(Request $request): ?Tenant
    {
        $user = $request->user();
        $tenant = $this->resolveTenantFromHost($request);

        if ($tenant !== null) {
            if ($user !== null && ! $user->tenants()->whereKey($tenant->getKey())->exists()) {
                abort(Response::HTTP_FORBIDDEN, 'You do not belong to this tenant.');
            }

            return $tenant;
        }

        return null;
    }

    protected function resolveTenantFromHost(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $baseDomain = config('app.tenant_base_domain');

        if (! is_string($baseDomain) || $baseDomain === '') {
            return null;
        }

        if ($host === $baseDomain) {
            return null;
        }

        $suffix = '.'.$baseDomain;

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        try {
            return Tenant::query()->where('slug', $subdomain)->firstOrFail();
        } catch (ModelNotFoundException) {
            abort(Response::HTTP_NOT_FOUND, 'Tenant not found.');
        }
    }
}
