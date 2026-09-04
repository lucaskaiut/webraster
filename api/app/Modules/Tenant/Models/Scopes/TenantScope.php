<?php

namespace App\Modules\Tenant\Models\Scopes;

use App\Modules\Tenant\Exceptions\TenantCouldNotBeResolved;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Resolution\Contracts\TenantResolverInterface;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! TenantContext::isResolved()) {
            return;
        }

        try {
            $tenantId = app(TenantResolverInterface::class)->currentTenantId();
        } catch (TenantCouldNotBeResolved) {
            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenancy', function (Builder $builder): Builder {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('forTenant', function (Builder $builder, Tenant|int $tenant): Builder {
            return $builder
                ->withoutGlobalScope($this)
                ->where(
                    $builder->getModel()->qualifyColumn('tenant_id'),
                    $tenant instanceof Tenant ? $tenant->getKey() : $tenant,
                );
        });
    }
}
