<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'full_name',
        'email',
        'date_of_birth',
        'address',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function campaignRecipients(): MorphMany
    {
        return $this->morphMany(CampaignRecipient::class, 'recipient');
    }
}
