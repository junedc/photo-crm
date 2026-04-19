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
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_starts_at' => 'date',
            'period_ends_at' => 'date',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
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
