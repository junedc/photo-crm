<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageHourlyPrice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'package_id',
        'hours',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'price' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
