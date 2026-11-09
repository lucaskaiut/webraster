<?php

namespace App\Modules\Tenant\Http\Middleware;

use App\Modules\Tenant\Exceptions\TenantDomainMismatch;
use App\Modules\Tenant\Exceptions\TenantInactive;
use App\Modules\Tenant\Resolution\TenantResolver;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\Tenant\Support\TenantDomainContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly CurrentTenant $context,
        private readonly TenantDomainContext $domain,
    ) {}

    /**
     * @throws TenantInactive
     * @throws TenantDomainMismatch
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        // No domínio de uma empresa, só a própria empresa pode ser acessada.
        $locked = $this->domain->lockedTenant($request, $request->user() ?? $request->user('sanctum'));

        if ($locked !== null && ! $locked->is($tenant)) {
            throw TenantDomainMismatch::make();
        }

        if (! $tenant->isActive()) {
            throw TenantInactive::make();
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
