<?php

namespace App\Modules\Report\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::REPORT_VIEW);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission(Permission::REPORT_EXPORT);
    }
}
