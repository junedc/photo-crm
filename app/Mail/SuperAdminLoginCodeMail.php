<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SuperAdminLoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $code,
        public CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Photobooth CRM admin login code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.super-admin-login-code',
        );
    }
}
