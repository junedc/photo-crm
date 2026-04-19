<?php

namespace App\Providers;

use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class, fn (): CurrentTenant => new CurrentTenant);
    }

     /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }
    }
}
