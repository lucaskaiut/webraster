<?php

namespace App\Modules\Crm\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Crm\Models\AiConfiguration;
use App\Modules\User\Models\User;

class AiConfigurationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CRM_AI_VIEW);
    }

    public function update(User $user, AiConfiguration $configuration): bool
    {
        return $user->hasPermission(Permission::CRM_AI_MANAGE);
    }
}
