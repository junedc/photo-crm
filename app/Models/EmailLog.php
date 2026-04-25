<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'recipient_email',
        'recipient_name',
        'recipient_type',
        'subject',
        'html_content',
        'text_content',
        'attachments',
        'mailable_class',
        'context_type',
        'context_id',
        'email_tracking_status_id',
        'status',
        'error_message',
        'related_email_log_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sourceLog(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_email_log_id');
    }

    public function emailTrackingStatus(): BelongsTo
    {
        return $this->belongsTo(WorkspaceStatus::class, 'email_tracking_status_id');
    }
}
