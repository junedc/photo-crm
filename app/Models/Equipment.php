<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipment extends Model
{
    use BelongsToTenant;

    protected $table = 'equipment';

    protected $fillable = [
        'tenant_id',
        'package_id',
        'name',
        'category',
        'serial_number',
        'description',
        'daily_rate',
        'maintenance_status_id',
        'maintenance_status',
        'last_maintained_at',
        'maintenance_notes',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'last_maintained_at' => 'date',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function maintenanceStatusRecord(): BelongsTo
    {
        return $this->belongsTo(WorkspaceStatus::class, 'maintenance_status_id');
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_equipment')
            ->withPivot('discount_percentage', 'discount_type', 'discount_value')
            ->withTimestamps();
    }

    public function discountedDailyRate(?float $discountPercentage = null): float
    {
        $rate = (float) ($this->daily_rate ?? 0);
        $discountPercentage = max(0, min(100, (float) ($discountPercentage ?? 0)));

        return round($rate * (1 - ($discountPercentage / 100)), 2);
    }

    public function discountedDailyRateForBooking(?string $discountType = null, mixed $discountValue = null, ?float $legacyPercentage = null): float
    {
        $rate = (float) ($this->daily_rate ?? 0);
        $normalizedType = $discountType === 'amount' ? 'amount' : 'percentage';

        if ($normalizedType === 'amount') {
            return round(max(0, $rate - max(0, (float) ($discountValue ?? 0))), 2);
        }

        return $this->discountedDailyRate($legacyPercentage ?? (float) ($discountValue ?? 0));
    }

    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class, 'discount_equipment')
            ->withTimestamps();
    }
}
