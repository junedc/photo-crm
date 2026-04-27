<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'invoice_number',
        'token',
        'total_amount',
        'amount_paid',
        'amounts_are',
        'line_description',
        'tax_rate',
        'invoice_status_id',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function invoiceStatus(): BelongsTo
    {
        return $this->belongsTo(WorkspaceStatus::class, 'invoice_status_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class)->orderBy('sequence');
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
