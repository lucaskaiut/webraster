<?php

namespace App\Modules\Checklist\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Checklist\Models\Checklist;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class ChecklistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CHECKLIST_READ);
    }

    public function view(User $user, Checklist $checklist): bool
    {
        return $this->sameTenant($checklist) && $user->hasPermission(Permission::CHECKLIST_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CHECKLIST_CREATE);
    }

    public function update(User $user, Checklist $checklist): bool
    {
        return $this->sameTenant($checklist) && $user->hasPermission(Permission::CHECKLIST_UPDATE);
    }

    public function delete(User $user, Checklist $checklist): bool
    {
        return $this->sameTenant($checklist) && $user->hasPermission(Permission::CHECKLIST_DELETE);
    }

    private function sameTenant(Checklist $checklist): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $checklist->tenant_id);
    }
}
