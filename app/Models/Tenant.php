<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'referral_code',
        'logo_path',
        'theme',
        'subscription_id',
        'subscription_enabled',
        'subscription_disabled_at',
        'platform_stripe_customer_id',
        'platform_stripe_payment_method_id',
        'contact_email',
        'contact_phone',
        'address',
        'invoice_deposit_percentage',
        'travel_free_kilometers',
        'travel_fee_per_kilometer',
        'packages_api_key',
        'stripe_secret',
        'stripe_webhook_secret',
        'stripe_currency',
        'quote_prefix',
        'invoice_prefix',
        'customer_package_discount_percentage',
    ];

    protected function casts(): array
    {
        return [
            'invoice_deposit_percentage' => 'decimal:2',
            'travel_free_kilometers' => 'decimal:2',
            'travel_fee_per_kilometer' => 'decimal:2',
            'customer_package_discount_percentage' => 'decimal:2',
            'subscription_enabled' => 'boolean',
            'subscription_disabled_at' => 'datetime',
            'stripe_secret' => 'encrypted',
            'stripe_webhook_secret' => 'encrypted',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'role_id'])
            ->withTimestamps();
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function taskStatuses(): HasMany
    {
        return $this->hasMany(TaskStatus::class);
    }

    public function workspaceStatuses(): HasMany
    {
        return $this->hasMany(WorkspaceStatus::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionCharges(): HasMany
    {
        return $this->hasMany(TenantSubscriptionCharge::class);
    }

    public function latestSubscriptionCharge()
    {
        return $this->hasOne(TenantSubscriptionCharge::class)->latestOfMany();
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function sentReferrals(): HasMany
    {
        return $this->hasMany(TenantReferral::class, 'referrer_tenant_id');
    }

    public function receivedReferral()
    {
        return $this->hasOne(TenantReferral::class, 'referred_tenant_id');
    }
}
