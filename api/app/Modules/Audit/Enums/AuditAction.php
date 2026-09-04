<?php

namespace App\Modules\Audit\Enums;

enum AuditAction: string
{
    case MasterLogin = 'master_login';
    case TenantSelected = 'tenant_selected';
    case TenantSwitched = 'tenant_switched';
}
