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
            ->withTimestamps();
    }

    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class, 'discount_equipment')
            ->withTimestamps();
    }
}
