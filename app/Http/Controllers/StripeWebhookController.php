<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\TenantSubscriptionCharge;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = (string) $request->getContent();
        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response('Invalid payload.', SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $signature = (string) $request->header('Stripe-Signature', '');
        $secret = $this->webhookSecretForEvent($event);

        if (! $this->isValidSignature($payload, $signature, $secret)) {
            return response('Invalid Stripe signature.', SymfonyResponse::HTTP_BAD_REQUEST);
        }

        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $this->handleCheckoutSessionCompleted($event['data']['object'] ?? []);
        }

        return response('Webhook received.', SymfonyResponse::HTTP_OK);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function webhookSecretForEvent(array $event): string
    {
        $session = $event['data']['object'] ?? [];

        if (! is_array($session)) {
            return (string) config('services.stripe.webhook_secret');
        }

        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];

        if (isset($metadata['tenant_subscription_charge_id'])) {
            return (string) config('services.platform_stripe.webhook_secret');
        }

        $invoiceId = isset($metadata['invoice_id']) ? (int) $metadata['invoice_id'] : null;

        if (! $invoiceId) {
            return (string) config('services.stripe.webhook_secret');
        }

        $invoice = Invoice::query()->with('tenant')->find($invoiceId);

        return (string) ($invoice?->tenant?->stripe_webhook_secret ?: config('services.stripe.webhook_secret'));
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleCheckoutSessionCompleted(array $session): void
    {
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $subscriptionChargeId = isset($metadata['tenant_subscription_charge_id']) ? (int) $metadata['tenant_subscription_charge_id'] : null;

        if ($subscriptionChargeId) {
            $this->handleSubscriptionChargeCompleted($session, $subscriptionChargeId);

            return;
        }

        $invoiceId = isset($metadata['invoice_id']) ? (int) $metadata['invoice_id'] : null;
        $installmentId = isset($metadata['installment_id']) ? (int) $metadata['installment_id'] : null;

        if (! $invoiceId || ! $installmentId) {
            return;
        }

        /** @var Invoice|null $invoice */
        $invoice = Invoice::query()->with(['installments', 'booking'])->find($invoiceId);

        if ($invoice === null) {
            return;
        }

        /** @var InvoiceInstallment|null $installment */
        $installment = $invoice->installments->firstWhere('id', $installmentId);

        if ($installment === null) {
            return;
        }

        if (($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        if ($installment->status !== 'paid') {
            $installment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        $invoice->load('installments');
        $amountPaid = (float) $invoice->installments->where('status', 'paid')->sum('amount');
        $invoice->update([
            'amount_paid' => number_format($amountPaid, 2, '.', ''),
            'status' => $amountPaid >= (float) $invoice->total_amount ? 'paid' : 'partially_paid',
        ]);

        $booking = $invoice->booking;

        if ($booking !== null && in_array($booking->status, ['pending', 'confirmed'], true)) {
            $booking->update([
                'status' => $amountPaid >= (float) $invoice->total_amount ? 'completed' : 'confirmed',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleSubscriptionChargeCompleted(array $session, int $chargeId): void
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $charge = TenantSubscriptionCharge::query()->with('tenant')->find($chargeId);

        if ($charge === null) {
            return;
        }

        if ($charge->status !== TenantSubscriptionCharge::STATUS_PAID) {
            $charge->update([
                'status' => TenantSubscriptionCharge::STATUS_PAID,
                'stripe_checkout_session_id' => (string) ($session['id'] ?? $charge->stripe_checkout_session_id),
                'stripe_payment_intent_id' => isset($session['payment_intent']) ? (string) $session['payment_intent'] : $charge->stripe_payment_intent_id,
                'paid_at' => now(),
            ]);
        }

        // Tenant access is intentionally manual. Payments update history only;
        // super admin decides whether a tenant should be enabled or disabled.
    }

    private function isValidSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($payload === '' || $signatureHeader === '' || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);

            if ($key === 't') {
                $timestamp = $value;
            }

            if ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
