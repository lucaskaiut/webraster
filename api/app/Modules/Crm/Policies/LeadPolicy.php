<?php

namespace App\Modules\Crm\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Crm\Models\Lead;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CRM_LEAD_VIEW);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->sameTenant($lead) && $user->hasPermission(Permission::CRM_LEAD_VIEW);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->sameTenant($lead) && $user->hasPermission(Permission::CRM_LEAD_UPDATE);
    }

    private function sameTenant(Lead $lead): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $lead->tenant_id);
    }
}
