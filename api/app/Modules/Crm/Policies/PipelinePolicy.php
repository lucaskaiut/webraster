<?php

namespace App\Modules\Crm\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Crm\Models\Pipeline;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class PipelinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CRM_PIPELINE_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CRM_PIPELINE_MANAGE);
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $pipeline->tenant_id)
            && $user->hasPermission(Permission::CRM_PIPELINE_MANAGE);
    }
}
