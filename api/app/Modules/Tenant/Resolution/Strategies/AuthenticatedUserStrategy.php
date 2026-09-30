<?php

namespace App\Modules\Tenant\Resolution\Strategies;

use App\Modules\Tenant\Exceptions\TenantAccessForbidden;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Resolution\Contracts\ResolutionStrategy;
use App\Modules\Tenant\Services\MasterTenantAccessService;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

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

        if ($header !== null && $header !== '') {
            return $this->access->resolveAccessibleTenant($user, $header);
        }

        // Token emitido no login guarda o tenant escolhido pelo usuário.
        return $this->tenantFromAccessToken($user) ?? $user->tenant;
    }

    private function tenantFromAccessToken(User $user): ?Tenant
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        $tenantId = $token->getAttribute('tenant_id');

        if ($tenantId === null) {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null || ! $this->access->canAccess($user, $tenant)) {
            return null;
        }

        return $tenant;
    }
}
