<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    public const BILLING_WEEKLY = 'weekly';
    public const BILLING_MONTHLY = 'monthly';
    public const BILLING_QUARTERLY = 'quarterly';
    public const BILLING_YEARLY = 'yearly';
    public const BILLING_FREE_FOR_LIFE = 'free_for_life';

    public const VALIDITY_WEEK = 'week';
    public const VALIDITY_MONTH = 'month';
    public const VALIDITY_YEAR = 'year';

    protected $fillable = [
        'name',
        'billing_period',
        'price',
        'currency',
        'validity_count',
        'validity_unit',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'validity_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function billingPeriods(): array
    {
        return [
            self::BILLING_WEEKLY => 'Weekly',
            self::BILLING_MONTHLY => 'Monthly',
            self::BILLING_QUARTERLY => 'Quarterly',
            self::BILLING_YEARLY => 'Yearly',
            self::BILLING_FREE_FOR_LIFE => 'Free for life',
        ];
    }

    public static function validityUnits(): array
    {
        return [
            self::VALIDITY_WEEK => 'Week(s)',
            self::VALIDITY_MONTH => 'Month(s)',
            self::VALIDITY_YEAR => 'Year(s)',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
