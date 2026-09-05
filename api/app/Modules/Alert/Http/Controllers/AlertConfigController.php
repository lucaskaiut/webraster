<?php

namespace App\Modules\Alert\Http\Controllers;

use App\Modules\Alert\Http\Requests\StoreAlertConfigRequest;
use App\Modules\Alert\Http\Requests\UpdateAlertConfigRequest;
use App\Modules\Alert\Http\Resources\AlertConfigResource;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class AlertConfigController extends ApiController
{
    public function __construct(private readonly AlertConfigService $service) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', AlertConfig::class);

        return $this->success(AlertConfigResource::collection($this->service->listForCurrentTenant()));
    }

    public function store(StoreAlertConfigRequest $request): JsonResponse
    {
        $this->authorize('create', AlertConfig::class);

        $config = $this->service->create($request->validated());

        return $this->created(AlertConfigResource::make($config), 'Alerta configurado.');
    }

    public function update(UpdateAlertConfigRequest $request, AlertConfig $alertConfig): JsonResponse
    {
        $this->authorize('update', $alertConfig);

        $config = $this->service->update($alertConfig, $request->validated());

        return $this->success(AlertConfigResource::make($config), 'Configuração atualizada.');
    }

    public function destroy(AlertConfig $alertConfig): JsonResponse
    {
        $this->authorize('delete', $alertConfig);

        $this->service->delete($alertConfig);

        return $this->success(null, 'Configuração removida.');
    }
}
