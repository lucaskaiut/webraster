<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Http\Resources\PublicTenantResource;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * Lista os tenants para a sincronização de marcas no build do aplicativo.
 * Protegido por token de build (services.build.token).
 */
class BuildTenantController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        $tenants = Tenant::query()
            ->whereNotNull('identifier')
            ->orderBy('name')
            ->get();

        return $this->success(PublicTenantResource::collection($tenants));
    }
}
