<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceInstallment extends Model
{
    protected $fillable = [
        'invoice_id',
        'sequence',
        'label',
        'due_date',
        'amount',
        'invoice_installment_status_id',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function installmentStatus(): BelongsTo
    {
        return $this->belongsTo(WorkspaceStatus::class, 'invoice_installment_status_id');
    }
}
