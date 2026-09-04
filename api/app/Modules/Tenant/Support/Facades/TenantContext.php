<?php

namespace App\Modules\Tenant\Support\Facades;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\CurrentTenant;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void set(Tenant $tenant)
 * @method static void forget()
 * @method static Tenant|null tenant()
 * @method static int|null tenantId()
 * @method static bool isResolved()
 *
 * @see CurrentTenant
 */
final class TenantContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CurrentTenant::class;
    }
}
