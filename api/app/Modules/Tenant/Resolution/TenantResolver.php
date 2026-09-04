<?php

namespace App\Modules\Tenant\Resolution;

use App\Modules\Tenant\Exceptions\TenantCouldNotBeResolved;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Resolution\Contracts\ResolutionStrategy;
use App\Modules\Tenant\Resolution\Contracts\TenantResolverInterface;
use App\Modules\Tenant\Support\CurrentTenant;
use Illuminate\Http\Request;

class TenantResolver implements TenantResolverInterface
{
    /**
     * @param  iterable<ResolutionStrategy>  $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
        private readonly CurrentTenant $context,
    ) {}

    /**
     * @throws TenantCouldNotBeResolved
     */
    public function resolve(Request $request): Tenant
    {
        foreach ($this->strategies as $strategy) {
            $tenant = $strategy->resolve($request);

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        throw TenantCouldNotBeResolved::make();
    }

    /**
     * @throws TenantCouldNotBeResolved
     */
    public function currentTenantId(): int
    {
        $tenantId = $this->context->tenantId();

        if ($tenantId === null) {
            throw TenantCouldNotBeResolved::make();
        }

        return $tenantId;
    }
}
