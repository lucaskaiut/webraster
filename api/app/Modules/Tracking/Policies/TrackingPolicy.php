<?php

namespace App\Modules\Tracking\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;

class TrackingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::TRACKING_READ);
    }
}
