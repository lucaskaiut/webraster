<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Http\Requests\UpdateAsaasConfigRequest;
use App\Modules\Finance\Http\Resources\TenantAsaasConfigResource;
use App\Modules\Finance\Models\TenantAsaasConfig;
use App\Modules\Finance\Services\AsaasConfigService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class FinanceAsaasConfigController extends ApiController
{
    public function __construct(private readonly AsaasConfigService $service) {}

    public function show(): JsonResponse
    {
        $this->authorize('viewAny', TenantAsaasConfig::class);

        $config = $this->service->get();

        if ($config === null) {
            return $this->success(null, 'Configuração Asaas ainda não definida.');
        }

        $this->authorize('view', $config);

        return $this->success(TenantAsaasConfigResource::make($config));
    }

    public function update(UpdateAsaasConfigRequest $request): JsonResponse
    {
        $this->authorize('update', TenantAsaasConfig::class);

        $config = $this->service->upsert($request->validated());

        return $this->success(TenantAsaasConfigResource::make($config), 'Configuração Asaas salva.');
    }
}
