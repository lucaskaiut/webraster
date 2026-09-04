<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Tenant\Exceptions\TenantAccessForbidden;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;

class TenantSwitchService
{
    public function __construct(
        private readonly MasterTenantAccessService $access,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @throws TenantAccessForbidden
     */
    public function select(User $user, string $tenantIdentifier): Tenant
    {
        if (! $user->is_master) {
            throw TenantAccessForbidden::make();
        }

        $tenant = $this->access->resolveAccessibleTenant($user, $tenantIdentifier);

        $this->audit->record($user, AuditAction::TenantSwitched, $tenant);

        return $tenant;
    }
}
