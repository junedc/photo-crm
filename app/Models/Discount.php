<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Discount extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'starts_at',
        'ends_at',
        'discount_type',
        'discount_value',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'discount_value' => 'decimal:2',
        ];
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'discount_package')
            ->withTimestamps();
    }

}
