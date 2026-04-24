<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'task_name',
        'task_duration_hours',
        'assigned_to',
        'task_status_id',
        'due_date',
        'date_started',
        'date_completed',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'task_duration_hours' => 'decimal:2',
            'due_date' => 'date',
            'date_started' => 'date',
            'date_completed' => 'date',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'task_status_id');
    }
}
