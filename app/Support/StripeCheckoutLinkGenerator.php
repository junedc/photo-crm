<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class StripeCheckoutLinkGenerator
{
    public function forInstallment(Invoice $invoice, InvoiceInstallment $installment): string
    {
        $invoice->loadMissing(['booking.package', 'tenant']);

        $secretKey = (string) $invoice->tenant?->stripe_secret;

        if ($secretKey === '') {
            throw ValidationException::withMessages([
                'stripe' => 'Tenant Stripe is not configured yet. Please add this workspace Stripe secret in Workspace Settings.',
            ]);
        }

        $booking = $invoice->booking;
        $currency = strtolower((string) ($invoice->tenant?->stripe_currency ?: 'aud'));
        $invoiceUrl = route('invoices.show', $invoice);
        $successUrl = $invoiceUrl.'?payment=success&installment='.$installment->id.'&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $invoiceUrl.'?payment=cancel&installment='.$installment->id;

        $response = Http::asForm()
            ->withBasicAuth($secretKey, '')
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'customer_email' => $booking?->customer_email,
                'metadata[invoice_id]' => (string) $invoice->id,
                'metadata[installment_id]' => (string) $installment->id,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => $currency,
                'line_items[0][price_data][unit_amount]' => (string) ((int) round(((float) $installment->amount) * 100)),
                'line_items[0][price_data][product_data][name]' => $invoice->invoice_number.' '.$installment->label,
                'line_items[0][price_data][product_data][description]' => $this->descriptionFor($invoice, $installment),
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'stripe' => 'Stripe checkout session could not be created. Please verify your Stripe keys and try again.',
            ]);
        }

        return (string) $response->json('url');
    }

    private function descriptionFor(Invoice $invoice, InvoiceInstallment $installment): string
    {
        $booking = $invoice->booking;
        $packageName = $booking?->package?->name ?? 'Booking';

        return sprintf(
            '%s for %s on %s',
            $installment->label,
            $packageName,
            $booking?->event_date?->format('d M Y') ?? 'your event date',
        );
    }
}
