<?php

use App\Http\Middleware\RequireCurrentTenant;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            SetCurrentTenant::class,
        ]);

        $middleware->alias([
            'tenant' => SetCurrentTenant::class,
            'tenant.required' => RequireCurrentTenant::class,
            'super.admin' => RequireSuperAdmin::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request): string => '/login');
        $middleware->redirectUsersTo(fn (Request $request): string => '/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
