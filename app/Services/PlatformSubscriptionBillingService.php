<?php

namespace App\Services;

use App\Mail\PlatformSubscriptionPaidMail;
use App\Mail\PlatformSubscriptionPaymentFailedMail;
use App\Mail\PlatformSubscriptionReminderMail;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantSubscriptionCharge;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class PlatformSubscriptionBillingService
{
    /**
     * @return array{reminders:int, charged:int, failed:int, skipped:int}
     */
    public function process(CarbonInterface|string|null $date = null): array
    {
        $today = CarbonImmutable::parse($date ?? now())->startOfDay();

        return [
            ...$this->sendUpcomingReminders($today),
            ...$this->chargeDueSubscriptions($today),
        ];
    }

    /**
     * @return array{reminders:int}
     */
    public function sendUpcomingReminders(CarbonImmutable $today): array
    {
        $sent = 0;
        $reminderDays = max(0, (int) config('platform-subscriptions.reminder_days', 5));
        $targetDate = $today->addDays($reminderDays);

        $this->billableTenants()->each(function (Tenant $tenant) use (&$sent, $targetDate): void {
            $subscription = $tenant->subscription;

            if (! $subscription instanceof Subscription) {
                return;
            }

            $charge = $this->upcomingChargeForReminder($tenant, $subscription, $targetDate);

            if (! $charge instanceof TenantSubscriptionCharge || $charge->reminder_sent_at !== null) {
                return;
            }

            $this->sendMail($tenant, new PlatformSubscriptionReminderMail($tenant, $charge));

            $charge->update(['reminder_sent_at' => now()]);
            $sent++;
        });

        return ['reminders' => $sent];
    }

    /**
     * @return array{charged:int, failed:int, skipped:int}
     */
    public function chargeDueSubscriptions(CarbonImmutable $today): array
    {
        $charged = 0;
        $failed = 0;
        $skipped = 0;

        $this->billableTenants()->each(function (Tenant $tenant) use (&$charged, &$failed, &$skipped, $today): void {
            $subscription = $tenant->subscription;

            if (! $subscription instanceof Subscription) {
                return;
            }

            $this->ensureDueChargeExists($tenant, $subscription, $today);
        });

        TenantSubscriptionCharge::query()
            ->with(['tenant', 'subscription'])
            ->whereIn('status', [TenantSubscriptionCharge::STATUS_PENDING, TenantSubscriptionCharge::STATUS_FAILED])
            ->where(function ($query) use ($today): void {
                $query
                    ->where(function ($pending) use ($today): void {
                        $pending
                            ->where('status', TenantSubscriptionCharge::STATUS_PENDING)
                            ->whereDate('period_starts_at', '<=', $today->toDateString());
                    })
                    ->orWhere(function ($retry) use ($today): void {
                        $retry
                            ->where('status', TenantSubscriptionCharge::STATUS_FAILED)
                            ->whereNotNull('next_retry_at')
                            ->where('next_retry_at', '<=', $today->endOfDay());
                    });
            })
            ->orderBy('period_starts_at')
            ->chunkById(50, function ($charges) use (&$charged, &$failed, &$skipped): void {
                foreach ($charges as $charge) {
                    if (! (bool) config('platform-subscriptions.auto_charge', true)) {
                        $skipped++;
                        continue;
                    }

                    $result = $this->attemptCharge($charge);

                    if ($result === 'paid') {
                        $charged++;
                    } elseif ($result === 'failed') {
                        $failed++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return compact('charged', 'failed', 'skipped');
    }

    public function syncPaidCheckoutSession(string $sessionId, ?Tenant $expectedTenant = null): ?TenantSubscriptionCharge
    {
        $secretKey = $this->stripeSecret();

        if ($sessionId === '' || $secretKey === '') {
            return null;
        }

        $sessionResponse = Http::withBasicAuth($secretKey, '')
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

        if ($sessionResponse->failed() || $sessionResponse->json('payment_status') !== 'paid') {
            return null;
        }

        $metadata = $sessionResponse->json('metadata') ?? [];
        $chargeId = isset($metadata['tenant_subscription_charge_id']) ? (int) $metadata['tenant_subscription_charge_id'] : null;

        if (! $chargeId) {
            return null;
        }

        $charge = TenantSubscriptionCharge::query()
            ->with('tenant')
            ->whereKey($chargeId)
            ->when($expectedTenant instanceof Tenant, fn ($query) => $query->where('tenant_id', $expectedTenant->id))
            ->first();

        if (! $charge instanceof TenantSubscriptionCharge || ! $charge->tenant instanceof Tenant) {
            return null;
        }

        $paymentIntentId = (string) ($sessionResponse->json('payment_intent') ?: '');
        $paymentMethodId = $this->paymentMethodForPaymentIntent($paymentIntentId);
        $customerId = (string) ($sessionResponse->json('customer') ?: $charge->tenant->platform_stripe_customer_id);

        $charge->tenant->update([
            'platform_stripe_customer_id' => $customerId ?: $charge->tenant->platform_stripe_customer_id,
            'platform_stripe_payment_method_id' => $paymentMethodId ?: $charge->tenant->platform_stripe_payment_method_id,
        ]);

        if ($charge->status !== TenantSubscriptionCharge::STATUS_PAID) {
            $charge->update([
                'status' => TenantSubscriptionCharge::STATUS_PAID,
                'stripe_checkout_session_id' => $sessionId,
                'stripe_payment_intent_id' => $paymentIntentId ?: $charge->stripe_payment_intent_id,
                'paid_at' => now(),
                'last_payment_error' => null,
                'next_retry_at' => null,
            ]);

            $this->sendMail($charge->tenant, new PlatformSubscriptionPaidMail($charge->tenant, $charge));
        }

        return $charge->fresh();
    }

    public function chargeForPeriod(Tenant $tenant, Subscription $subscription, CarbonInterface $date): TenantSubscriptionCharge
    {
        [$periodStartsAt, $periodEndsAt] = $this->periodFor($subscription, $date);

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
                'status' => (float) $subscription->price <= 0
                    ? TenantSubscriptionCharge::STATUS_WAIVED
                    : TenantSubscriptionCharge::STATUS_PENDING,
                'paid_at' => (float) $subscription->price <= 0 ? now() : null,
                'notes' => (float) $subscription->price <= 0 ? 'Free subscription period.' : null,
            ],
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable|null}
     */
    public function periodFor(Subscription $subscription, CarbonInterface $date): array
    {
        $startsAt = CarbonImmutable::parse($date)->startOfDay();
        $count = max(1, (int) ($subscription->validity_count ?: 1));
        $unit = $subscription->validity_unit;

        $endsAt = match ($unit) {
            Subscription::VALIDITY_WEEK => $startsAt->addWeeks($count)->subDay(),
            Subscription::VALIDITY_MONTH => $startsAt->addMonthsNoOverflow($count)->subDay(),
            Subscription::VALIDITY_YEAR => $startsAt->addYearsNoOverflow($count)->subDay(),
            default => match ($subscription->billing_period) {
                Subscription::BILLING_WEEKLY => $startsAt->addWeek()->subDay(),
                Subscription::BILLING_MONTHLY => $startsAt->addMonthNoOverflow()->subDay(),
                Subscription::BILLING_QUARTERLY => $startsAt->addMonthsNoOverflow(3)->subDay(),
                Subscription::BILLING_YEARLY => $startsAt->addYearNoOverflow()->subDay(),
                default => null,
            },
        };

        return [$startsAt, $endsAt];
    }

    private function upcomingChargeForReminder(Tenant $tenant, Subscription $subscription, CarbonImmutable $targetDate): ?TenantSubscriptionCharge
    {
        $existingCharge = $this->latestChargeFor($tenant, $subscription);

        if (! $existingCharge instanceof TenantSubscriptionCharge) {
            return null;
        }

        if ($existingCharge->status === TenantSubscriptionCharge::STATUS_PENDING && $existingCharge->period_starts_at?->isSameDay($targetDate)) {
            return (float) $existingCharge->amount > 0 ? $existingCharge : null;
        }

        if (! in_array($existingCharge->status, [TenantSubscriptionCharge::STATUS_PAID, TenantSubscriptionCharge::STATUS_WAIVED], true)) {
            return null;
        }

        $nextStartsAt = $this->nextPeriodStart($existingCharge);

        if (! $nextStartsAt instanceof CarbonImmutable || ! $nextStartsAt->isSameDay($targetDate)) {
            return null;
        }

        $charge = $this->chargeForPeriod($tenant, $subscription, $nextStartsAt);

        return (float) $charge->amount > 0 && $charge->status !== TenantSubscriptionCharge::STATUS_WAIVED ? $charge : null;
    }

    private function ensureDueChargeExists(Tenant $tenant, Subscription $subscription, CarbonImmutable $today): ?TenantSubscriptionCharge
    {
        $existingCharge = $this->latestChargeFor($tenant, $subscription);

        if (! $existingCharge instanceof TenantSubscriptionCharge) {
            return $this->chargeForPeriod($tenant, $subscription, $today);
        }

        if (! in_array($existingCharge->status, [TenantSubscriptionCharge::STATUS_PAID, TenantSubscriptionCharge::STATUS_WAIVED], true)) {
            return $existingCharge;
        }

        $nextStartsAt = $this->nextPeriodStart($existingCharge);

        if ($nextStartsAt instanceof CarbonImmutable && $nextStartsAt->lessThanOrEqualTo($today)) {
            return $this->chargeForPeriod($tenant, $subscription, $nextStartsAt);
        }

        return $existingCharge;
    }

    private function latestChargeFor(Tenant $tenant, Subscription $subscription): ?TenantSubscriptionCharge
    {
        return $tenant->subscriptionCharges()
            ->where('subscription_id', $subscription->id)
            ->latest('period_starts_at')
            ->first();
    }

    private function nextPeriodStart(TenantSubscriptionCharge $charge): ?CarbonImmutable
    {
        if ($charge->period_ends_at === null) {
            return null;
        }

        return CarbonImmutable::parse($charge->period_ends_at)->addDay()->startOfDay();
    }

    private function attemptCharge(TenantSubscriptionCharge $charge): string
    {
        $charge->loadMissing('tenant');
        $tenant = $charge->tenant;

        if (! $tenant instanceof Tenant) {
            return 'skipped';
        }

        $secretKey = $this->stripeSecret();
        $customerId = (string) $tenant->platform_stripe_customer_id;
        $paymentMethodId = (string) $tenant->platform_stripe_payment_method_id;

        if ($secretKey === '' || $customerId === '' || $paymentMethodId === '') {
            $this->markFailed($charge, 'No saved platform payment method is available for automatic billing.');

            return 'failed';
        }

        $response = Http::asForm()
            ->withBasicAuth($secretKey, '')
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => (string) ((int) round(((float) $charge->amount) * 100)),
                'currency' => strtolower($charge->currency),
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'off_session' => 'true',
                'confirm' => 'true',
                'metadata[tenant_subscription_charge_id]' => (string) $charge->id,
                'metadata[tenant_id]' => (string) $tenant->id,
                'description' => $charge->subscription_name.' subscription for '.$tenant->name,
            ]);

        if ($response->successful() && $response->json('status') === 'succeeded') {
            $charge->update([
                'status' => TenantSubscriptionCharge::STATUS_PAID,
                'stripe_payment_intent_id' => (string) $response->json('id'),
                'paid_at' => now(),
                'last_payment_error' => null,
                'next_retry_at' => null,
            ]);

            $this->sendMail($tenant, new PlatformSubscriptionPaidMail($tenant, $charge->fresh()));

            return 'paid';
        }

        $this->markFailed($charge, (string) ($response->json('error.message') ?: 'Stripe could not process the payment.'));

        return 'failed';
    }

    private function markFailed(TenantSubscriptionCharge $charge, string $message): void
    {
        $retryDays = max(1, (int) config('platform-subscriptions.retry_days', 5));

        $charge->update([
            'status' => TenantSubscriptionCharge::STATUS_FAILED,
            'payment_attempts' => $charge->payment_attempts + 1,
            'failure_notified_at' => now(),
            'next_retry_at' => now()->addDays($retryDays),
            'last_payment_error' => $message,
        ]);

        $charge->loadMissing('tenant');

        if ($charge->tenant instanceof Tenant) {
            $this->sendMail($charge->tenant, new PlatformSubscriptionPaymentFailedMail($charge->tenant, $charge->fresh(), $retryDays));
        }
    }

    private function paymentMethodForPaymentIntent(string $paymentIntentId): ?string
    {
        $secretKey = $this->stripeSecret();

        if ($paymentIntentId === '' || $secretKey === '') {
            return null;
        }

        $response = Http::withBasicAuth($secretKey, '')
            ->get("https://api.stripe.com/v1/payment_intents/{$paymentIntentId}");

        if ($response->failed()) {
            return null;
        }

        return $response->json('payment_method');
    }

    private function billableTenants()
    {
        return Tenant::query()
            ->with('subscription')
            ->where('subscription_enabled', true)
            ->whereNotNull('subscription_id')
            ->whereHas('subscription', function ($query): void {
                $query
                    ->where('is_active', true)
                    ->where('billing_period', '!=', Subscription::BILLING_FREE_FOR_LIFE);
            })
            ->get();
    }

    private function sendMail(Tenant $tenant, object $mail): void
    {
        $recipients = collect([$tenant->contact_email])
            ->merge($tenant->users()->wherePivot('role', 'owner')->pluck('email'))
            ->push(config('platform-subscriptions.admin_email'))
            ->filter()
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Mail::to($recipients->all())->send($mail);
    }

    private function stripeSecret(): string
    {
        return (string) config('services.platform_stripe.secret');
    }
}
