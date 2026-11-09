<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Http\Resources\PublicTenantResource;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\TenantDomainContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Identificação do tenant pelo usuário antes do login (dados públicos).
 */
class PublicTenantController extends ApiController
{
    public function __invoke(string $identifier): JsonResponse
    {
        $tenant = Tenant::query()
            ->where('identifier', Str::lower(trim($identifier)))
            ->firstOrFail();

        return $this->success(PublicTenantResource::make($tenant));
    }

    /**
     * Identidade visual da empresa dona do domínio (tela de login do painel).
     */
    public function byDomain(string $domain, TenantDomainContext $domains): JsonResponse
    {
        $tenant = $domains->findByHost($domain);

        abort_if($tenant === null, 404);

        return $this->success(PublicTenantResource::make($tenant));
    }
}
