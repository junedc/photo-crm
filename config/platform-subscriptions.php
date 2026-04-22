<?php

return [
    'auto_charge' => env('PLATFORM_SUBSCRIPTION_AUTO_CHARGE', true),
    'reminder_days' => (int) env('PLATFORM_SUBSCRIPTION_REMINDER_DAYS', 5),
    'retry_days' => (int) env('PLATFORM_SUBSCRIPTION_RETRY_DAYS', 5),
    'schedule_time' => env('PLATFORM_SUBSCRIPTION_SCHEDULE_TIME', '09:00'),
    'admin_email' => env('PLATFORM_SUBSCRIPTION_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS')),
];
