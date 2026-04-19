<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignResult extends Model
{
    protected $fillable = [
        'campaign_id',
        'campaign_recipient_id',
        'email',
        'name',
        'token',
        'status',
        'sent_at',
        'opened_at',
        'bounced_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'bounced_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignRecipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class);
    }

}
