<?php

use App\Services\PlatformSubscriptionBillingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('platform-subscriptions:process {--date= : Run billing as if today is this date, YYYY-MM-DD}', function () {
    $date = $this->option('date');
    $result = app(PlatformSubscriptionBillingService::class)->process($date ?: null);

    $this->info(sprintf(
        'Platform subscription billing complete. Reminders: %d, charged: %d, failed: %d, skipped: %d.',
        $result['reminders'],
        $result['charged'],
        $result['failed'],
        $result['skipped'],
    ));
})->purpose('Send platform subscription reminders, collect due payments, and retry failed payments');

Schedule::command('platform-subscriptions:process')
    ->dailyAt((string) config('platform-subscriptions.schedule_time', '09:00'));
