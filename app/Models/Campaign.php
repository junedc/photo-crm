<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'template_id',
        'subject',
        'preheader',
        'headline',
        'body',
        'button_text',
        'button_url',
        'status',
        'sent_count',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(CampaignResult::class);
    }
}
