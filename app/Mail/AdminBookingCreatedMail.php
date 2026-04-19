<?php

namespace App\Mail;

use App\Models\Booking;
use App\Support\BookingAddonsPdfAttachment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminBookingCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?BookingAddonsPdfAttachment $addonsPdf = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New quote request for '.$this->booking->package?->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.admin-created',
        );
    }

    
    public function attachments(): array
    {
        if ($this->addonsPdf === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->addonsPdf->content, $this->addonsPdf->name)
                ->withMime('application/pdf'),
        ];
    }
}
