<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\ClientPortalAccess;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientPortalInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public Booking $booking,
        public ClientPortalAccess $access,
        public string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.$this->tenant->name.' client portal access',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-portal.invite',
        );
    }
}
