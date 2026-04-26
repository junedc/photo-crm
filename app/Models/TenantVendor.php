<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantVendor extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'company_name',
        'address',
        'mobile_number',
        'service_type',
        'services_offered',
        'is_active',
        'email',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'services_offered' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id')
            ->where('assignee_type', Task::ASSIGNEE_VENDOR);
    }
}
