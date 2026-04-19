<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if ($model->tenant_id !== null) {
                return;
            }

            $tenantId = app(CurrentTenant::class)->id();

            if ($tenantId !== null) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
