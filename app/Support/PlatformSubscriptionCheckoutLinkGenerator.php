<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantSubscriptionCharge;
use App\Services\PlatformSubscriptionBillingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PlatformSubscriptionCheckoutLinkGenerator
{
    public function __construct(private readonly PlatformSubscriptionBillingService $billingService)
    {
    }

    public function checkoutUrlFor(Tenant $tenant, Subscription $subscription): string
    {
        $charge = $this->billingService->chargeForPeriod($tenant, $subscription, now());

        if (in_array($charge->status, [TenantSubscriptionCharge::STATUS_PAID, TenantSubscriptionCharge::STATUS_WAIVED], true)) {
            return route('settings.index');
        }

        if ((float) $charge->amount <= 0) {
            $charge->update([
                'status' => TenantSubscriptionCharge::STATUS_WAIVED,
                'paid_at' => now(),
                'notes' => 'Free subscription period.',
            ]);

            return route('settings.index');
        }

        $secretKey = (string) config('services.platform_stripe.secret');

        if ($secretKey === '') {
            throw ValidationException::withMessages([
                'subscription' => 'Platform subscription Stripe is not configured yet.',
            ]);
        }

        $settingsUrl = route('settings.index');

        $payload = [
            'mode' => 'payment',
            'success_url' => $settingsUrl.'?subscription_payment=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $settingsUrl.'?subscription_payment=cancel',
            'payment_intent_data[setup_future_usage]' => 'off_session',
            'metadata[tenant_subscription_charge_id]' => (string) $charge->id,
            'metadata[tenant_id]' => (string) $tenant->id,
            'metadata[subscription_id]' => (string) $subscription->id,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($charge->currency),
            'line_items[0][price_data][unit_amount]' => (string) ((int) round(((float) $charge->amount) * 100)),
            'line_items[0][price_data][product_data][name]' => $charge->subscription_name.' subscription',
            'line_items[0][price_data][product_data][description]' => $this->descriptionFor($tenant, $charge),
        ];

        if (filled($tenant->platform_stripe_customer_id)) {
            $payload['customer'] = $tenant->platform_stripe_customer_id;
        } else {
            $payload['customer_creation'] = 'always';

            if (filled($tenant->contact_email)) {
                $payload['customer_email'] = $tenant->contact_email;
            }
        }

        $response = Http::asForm()
            ->withBasicAuth($secretKey, '')
            ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'subscription' => 'Subscription checkout session could not be created. Please verify platform Stripe keys.',
            ]);
        }

        $charge->update([
            'stripe_checkout_session_id' => (string) $response->json('id'),
        ]);

        return (string) $response->json('url');
    }

    private function descriptionFor(Tenant $tenant, TenantSubscriptionCharge $charge): string
    {
        $period = $charge->period_ends_at
            ? $charge->period_starts_at->format('d M Y').' to '.$charge->period_ends_at->format('d M Y')
            : 'No expiry';

        return "{$tenant->name} platform subscription for {$period}";
    }
}
