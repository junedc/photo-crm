<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'subscriber_group_id',
        'recipient_type',
        'recipient_id',
    ];

    public function subscriberGroup(): BelongsTo
    {
        return $this->belongsTo(SubscriberGroup::class);
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
