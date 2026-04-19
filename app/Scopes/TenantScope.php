<?php

namespace App\Scopes;

use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
