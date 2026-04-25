<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientPortalAccess extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'granted_by_user_id',
        'customer_email',
        'customer_name',
        'invite_token',
        'granted_at',
        'last_notified_at',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ClientPortalCode::class);
    }
}
