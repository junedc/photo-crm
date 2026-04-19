<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Tenant $tenant,
        public string $code,
        public CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.$this->tenant->name.' login verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.login-code',
        );
    }
}
