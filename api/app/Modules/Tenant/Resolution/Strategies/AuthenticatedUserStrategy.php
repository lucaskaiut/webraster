<?php

namespace App\Modules\Tenant\Resolution\Strategies;

use App\Modules\Tenant\Exceptions\TenantAccessForbidden;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Resolution\Contracts\ResolutionStrategy;
use App\Modules\Tenant\Services\MasterTenantAccessService;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;

class AuthenticatedUserStrategy implements ResolutionStrategy
{
    public const HEADER = 'X-Tenant-Id';

    public function __construct(
        private readonly MasterTenantAccessService $access,
    ) {}

    /**
     * @throws TenantAccessForbidden
     */
    public function resolve(Request $request): ?Tenant
    {
        $user = $request->user() ?? $request->user('sanctum');

        if (! $user instanceof User) {
            return null;
        }

        if (! $user->is_master) {
            return $user->tenant;
        }

        $header = $request->header(self::HEADER);

        if ($header === null || $header === '') {
            return $user->tenant;
        }

        return $this->access->resolveAccessibleTenant($user, $header);
    }
}
