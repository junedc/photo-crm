<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Lead extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'token',
        'customer_name',
        'customer_email',
        'customer_phone',
        'event_date',
        'venue',
        'event_location',
        'notes',
        'status',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $lead): void {
            if (blank($lead->token)) {
                $lead->token = (string) Str::uuid();
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function campaignRecipients(): MorphMany
    {
        return $this->morphMany(CampaignRecipient::class, 'recipient');
    }
}
