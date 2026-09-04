<?php

namespace App\Modules\Tenant\Http\Middleware;

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

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->set($this->resolver->resolve($request));

        return $next($request);
    }
}
