<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantSubscriptionCharge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PlatformSubscriptionCheckoutLinkGenerator
{
    public function checkoutUrlFor(Tenant $tenant, Subscription $subscription): string
    {
        $charge = $this->chargeForCurrentPeriod($tenant, $subscription);

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

        $response = Http::asForm()
            ->withBasicAuth($secretKey, '')
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $settingsUrl.'?subscription_payment=success',
                'cancel_url' => $settingsUrl.'?subscription_payment=cancel',
                'customer_email' => $tenant->contact_email,
                'metadata[tenant_subscription_charge_id]' => (string) $charge->id,
                'metadata[tenant_id]' => (string) $tenant->id,
                'metadata[subscription_id]' => (string) $subscription->id,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($charge->currency),
                'line_items[0][price_data][unit_amount]' => (string) ((int) round(((float) $charge->amount) * 100)),
                'line_items[0][price_data][product_data][name]' => $charge->subscription_name.' subscription',
                'line_items[0][price_data][product_data][description]' => $this->descriptionFor($tenant, $charge),
            ]);

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

    public function chargeForCurrentPeriod(Tenant $tenant, Subscription $subscription): TenantSubscriptionCharge
    {
        [$periodStartsAt, $periodEndsAt] = $this->periodFor($subscription);

        return TenantSubscriptionCharge::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'period_starts_at' => $periodStartsAt->toDateString(),
            ],
            [
                'subscription_name' => $subscription->name,
                'billing_period' => $subscription->billing_period,
                'period_ends_at' => $periodEndsAt?->toDateString(),
                'amount' => $subscription->billing_period === Subscription::BILLING_FREE_FOR_LIFE ? 0 : $subscription->price,
                'currency' => strtoupper($subscription->currency ?: config('services.platform_stripe.currency', 'USD')),
                'status' => TenantSubscriptionCharge::STATUS_PENDING,
            ],
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable|null}
     */
    private function periodFor(Subscription $subscription): array
    {
        $now = CarbonImmutable::now();

        return match ($subscription->billing_period) {
            Subscription::BILLING_WEEKLY => [$now->startOfWeek(), $now->endOfWeek()],
            Subscription::BILLING_MONTHLY => [$now->startOfMonth(), $now->endOfMonth()],
            Subscription::BILLING_QUARTERLY => [$now->startOfQuarter(), $now->endOfQuarter()],
            Subscription::BILLING_YEARLY => [$now->startOfYear(), $now->endOfYear()],
            default => [$now->startOfDay(), null],
        };
    }

    private function descriptionFor(Tenant $tenant, TenantSubscriptionCharge $charge): string
    {
        $period = $charge->period_ends_at
            ? $charge->period_starts_at->format('d M Y').' to '.$charge->period_ends_at->format('d M Y')
            : 'No expiry';

        return "{$tenant->name} platform subscription for {$period}";
    }
}
