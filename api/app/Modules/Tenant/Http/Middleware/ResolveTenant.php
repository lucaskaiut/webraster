<?php

namespace App\Modules\Tenant\Http\Middleware;

use App\Modules\Tenant\Exceptions\TenantInactive;
use App\Modules\Tenant\Resolution\TenantResolver;
use App\Modules\Tenant\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly CurrentTenant $context,
    ) {}

    /**
     * @throws TenantInactive
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        if (! $tenant->isActive()) {
            throw TenantInactive::make();
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
