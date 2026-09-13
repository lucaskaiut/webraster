<?php

namespace App\Modules\VehicleData\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\VehicleData\Http\Requests\UpdateVehicleDataConfigRequest;
use App\Modules\VehicleData\Models\TenantVehicleDataConfig;
use App\Modules\VehicleData\Services\VehicleDataConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleDataConfigController extends ApiController
{
    public function __construct(private readonly VehicleDataConfigService $service) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TenantVehicleDataConfig::class);

        $provider = $request->string('provider')->toString() ?: null;

        return $this->success($this->service->show($provider));
    }

    public function update(UpdateVehicleDataConfigRequest $request): JsonResponse
    {
        $this->authorize('update', TenantVehicleDataConfig::class);

        return $this->success($this->service->upsert($request->validated()), 'Configuração do provedor salva.');
    }
}
