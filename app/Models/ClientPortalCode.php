<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPortalCode extends Model
{
    protected $fillable = [
        'client_portal_access_id',
        'email',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function access(): BelongsTo
    {
        return $this->belongsTo(ClientPortalAccess::class, 'client_portal_access_id');
    }
}
