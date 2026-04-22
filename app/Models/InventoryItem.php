<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InventoryItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'addon_category',
        'sku',
        'description',
        'quantity',
        'unit_price',
        'duration',
        'maintenance_status',
        'last_maintained_at',
        'maintenance_notes',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'last_maintained_at' => 'date',
        ];
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'inventory_item_package')
            ->withTimestamps();
    }
}
