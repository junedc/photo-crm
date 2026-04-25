<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InventoryItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'type',
        'inventory_item_category_id',
        'addon_category',
        'is_publicly_displayed',
        'sku',
        'description',
        'quantity',
        'unit_price',
        'discount_percentage',
        'duration',
        'due_days_before_event',
        'maintenance_status',
        'last_maintained_at',
        'maintenance_notes',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'inventory_item_category_id' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'is_publicly_displayed' => 'boolean',
            'due_days_before_event' => 'integer',
            'last_maintained_at' => 'date',
        ];
    }

    public function discountedUnitPrice(): float
    {
        $price = (float) ($this->unit_price ?? 0);
        $discountPercentage = max(0, min(100, (float) ($this->discount_percentage ?? 0)));

        return round($price * (1 - ($discountPercentage / 100)), 2);
    }

    public function discountedUnitPriceForBooking(?float $discountPercentage = null): float
    {
        $price = $this->discountedUnitPrice();
        $discountPercentage = max(0, min(100, (float) ($discountPercentage ?? 0)));

        return round($price * (1 - ($discountPercentage / 100)), 2);
    }

    public function discountedUnitPriceForBookingSelection(?string $discountType = null, mixed $discountValue = null, ?float $legacyPercentage = null): float
    {
        $price = $this->discountedUnitPrice();
        $normalizedType = $discountType === 'amount' ? 'amount' : 'percentage';

        if ($normalizedType === 'amount') {
            return round(max(0, $price - max(0, (float) ($discountValue ?? 0))), 2);
        }

        return $this->discountedUnitPriceForBooking($legacyPercentage ?? (float) ($discountValue ?? 0));
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'inventory_item_package')
            ->withTimestamps();
    }

    public function inventoryItemCategory(): BelongsTo
    {
        return $this->belongsTo(InventoryItemCategory::class);
    }
}
