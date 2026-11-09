<?php

namespace App\Modules\Tenant\Enums;

enum TenantDomainStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case FAILED = 'failed';
}
