<?php

use App\Mail\AdminBookingCreatedMail;
use App\Mail\CampaignMail;
use App\Mail\CustomerBookingCreatedMail;
use App\Mail\InvoiceIssuedMail;
use App\Mail\LoginVerificationCodeMail;
use App\Mail\ClientPortalCodeMail;
use App\Mail\ClientPortalInviteMail;
use App\Mail\PlatformSubscriptionPaidMail;
use App\Mail\PlatformSubscriptionPaymentFailedMail;
use App\Mail\PlatformSubscriptionReminderMail;
use App\Mail\SuperAdminLoginCodeMail;

return [
    'mailables' => [
        LoginVerificationCodeMail::class => [
            'title' => 'Login verification code',
            'track' => false,
        ],
        ClientPortalInviteMail::class => [
            'title' => 'Client portal access',
            'track' => false,
        ],
        ClientPortalCodeMail::class => [
            'title' => 'Client portal code',
            'track' => false,
        ],
        SuperAdminLoginCodeMail::class => [
            'title' => 'Super admin login code',
            'track' => false,
        ],
        AdminBookingCreatedMail::class => [
            'title' => 'New quote request',
            'track' => true,
        ],
        CustomerBookingCreatedMail::class => [
            'title' => 'Customer quote ready',
            'track' => true,
        ],
        InvoiceIssuedMail::class => [
            'title' => 'Invoice issued',
            'track' => true,
        ],
        CampaignMail::class => [
            'title' => 'Campaign email',
            'track' => true,
        ],
        PlatformSubscriptionReminderMail::class => [
            'title' => 'Upcoming subscription payment',
            'track' => true,
        ],
        PlatformSubscriptionPaidMail::class => [
            'title' => 'Subscription payment received',
            'track' => true,
        ],
        PlatformSubscriptionPaymentFailedMail::class => [
            'title' => 'Subscription payment failed',
            'track' => true,
        ],
    ],
];
