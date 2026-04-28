<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Booking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'package_id',
        'package_price',
        'discount_id',
        'discount_amount',
        'booking_discount_type',
        'booking_discount_value',
        'booking_discount_source',
        'booking_kind',
        'entry_name',
        'entry_description',
        'customer_name',
        'customer_email',
        'customer_phone',
        'event_type',
        'venue',
        'event_date',
        'start_time',
        'end_time',
        'total_hours',
        'event_location',
        'travel_distance_km',
        'travel_fee',
        'notes',
        'booking_status_id',
        'status',
        'quote_token',
        'quote_number',
        'quote_response_status_id',
        'customer_response_status',
        'customer_responded_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'package_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'booking_discount_value' => 'decimal:2',
            'total_hours' => 'decimal:2',
            'travel_distance_km' => 'decimal:2',
            'travel_fee' => 'decimal:2',
            'customer_responded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $booking): void {
            if (blank($booking->quote_token)) {
                $booking->quote_token = (string) Str::uuid();
            }

            if (blank($booking->customer_response_status)) {
                $booking->customer_response_status = 'pending';
            }
        });

        static::created(function (self $booking): void {
            if (blank($booking->quote_number)) {
                $tenant = Tenant::query()->withoutGlobalScopes()->find($booking->tenant_id);
                $prefix = $tenant?->quote_prefix ?: 'QT';

                $booking->forceFill([
                    'quote_number' => sprintf('%s-%06d', strtoupper($prefix), $booking->id),
                ])->saveQuietly();
            }
        });
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function bookingStatus(): BelongsTo
    {
        return $this->belongsTo(WorkspaceStatus::class, 'booking_status_id');
    }

    public function quoteResponseStatus(): BelongsTo
    {
        return $this->belongsTo(WorkspaceStatus::class, 'quote_response_status_id');
    }

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'booking_inventory_item')
            ->withPivot('discount_percentage', 'discount_type', 'discount_value')
            ->withTimestamps();
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'booking_equipment')
            ->withPivot('discount_percentage', 'discount_type', 'discount_value')
            ->withTimestamps();
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function clientPortalDesign(): HasOne
    {
        return $this->hasOne(ClientPortalDesign::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function clientPortalTaskUpdates(): HasMany
    {
        return $this->hasMany(ClientPortalTaskUpdate::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BookingDocument::class)->latest();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(BookingContact::class)->latest();
    }
}
