<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\TenantSubscriptionCharge;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformSubscriptionPaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public TenantSubscriptionCharge $charge,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thank you, your subscription payment was received',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.platform-subscriptions.paid',
        );
    }
}
