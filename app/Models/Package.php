<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Package extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'base_price',
        'photo_path',
        'package_status_id',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'package_id');
    }

    public function packageStatus(): BelongsTo
    {
        return $this->belongsTo(WorkspaceStatus::class, 'package_status_id');
    }

    public function hourlyPrices(): HasMany
    {
        return $this->hasMany(PackageHourlyPrice::class)->orderBy('hours');
    }

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'inventory_item_package')
            ->withTimestamps();
    }

    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class, 'discount_package')
            ->withTimestamps();
    }
}
