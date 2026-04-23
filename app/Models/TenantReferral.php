<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantReferral extends Model
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_REWARDED = 'rewarded';

    protected $fillable = [
        'referrer_tenant_id',
        'referred_tenant_id',
        'referral_code',
        'referred_workspace_name',
        'referred_owner_email',
        'status',
        'qualified_at',
        'rewarded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qualified_at' => 'datetime',
            'rewarded_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_REGISTERED => 'Registered',
            self::STATUS_QUALIFIED => 'Qualified',
            self::STATUS_REWARDED => 'Rewarded',
        ];
    }

    public function referrerTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'referrer_tenant_id');
    }

    public function referredTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'referred_tenant_id');
    }
}
