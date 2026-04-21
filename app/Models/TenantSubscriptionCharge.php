<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscriptionCharge extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_WAIVED = 'waived';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'subscription_name',
        'billing_period',
        'period_starts_at',
        'period_ends_at',
        'amount',
        'currency',
        'status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'reminder_sent_at',
        'failure_notified_at',
        'next_retry_at',
        'paid_at',
        'payment_attempts',
        'last_payment_error',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_starts_at' => 'date',
            'period_ends_at' => 'date',
            'amount' => 'decimal:2',
            'reminder_sent_at' => 'datetime',
            'failure_notified_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'paid_at' => 'datetime',
            'payment_attempts' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
