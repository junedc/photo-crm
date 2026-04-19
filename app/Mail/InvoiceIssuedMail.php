<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public InvoiceInstallment $installment,
        public string $stripeCheckoutUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice '.$this->invoice->invoice_number.' for your booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.issued',
        );
    }
}
