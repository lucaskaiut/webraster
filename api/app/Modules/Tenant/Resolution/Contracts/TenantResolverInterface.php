<?php

namespace App\Modules\Tenant\Resolution\Contracts;

use App\Modules\Tenant\Exceptions\TenantCouldNotBeResolved;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;

interface TenantResolverInterface
{
    /**
     * @throws TenantCouldNotBeResolved
     */
    public function resolve(Request $request): Tenant;

    /**
     * Tenant ativo no contexto da requisição atual.
     *
     * @throws TenantCouldNotBeResolved
     */
    public function currentTenantId(): int;
}
