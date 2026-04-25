<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const ASSIGNEE_USER = 'user';
    public const ASSIGNEE_VENDOR = 'vendor';
    public const ASSIGNEE_CUSTOMER = 'customer';

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'inventory_item_id',
        'is_booking_action',
        'task_name',
        'task_duration_hours',
        'assignee_type',
        'assignee_id',
        'task_status_id',
        'due_date',
        'date_started',
        'date_completed',
        'notification_dismissed_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'task_duration_hours' => 'decimal:2',
            'is_booking_action' => 'boolean',
            'due_date' => 'date',
            'date_started' => 'date',
            'date_completed' => 'date',
            'notification_dismissed_at' => 'datetime',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function assigneeVendor(): BelongsTo
    {
        return $this->belongsTo(TenantVendor::class, 'assignee_id');
    }

    public function assigneeCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'assignee_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'task_status_id');
    }

    public function clientPortalUpdates(): HasMany
    {
        return $this->hasMany(ClientPortalTaskUpdate::class)->latest();
    }

    public static function assigneeTypes(): array
    {
        return [
            self::ASSIGNEE_USER,
            self::ASSIGNEE_VENDOR,
            self::ASSIGNEE_CUSTOMER,
        ];
    }
}
