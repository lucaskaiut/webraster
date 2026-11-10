<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Http\Requests\UpdateFinanceCollectionSettingRequest;
use App\Modules\Finance\Http\Resources\FinanceCollectionSettingResource;
use App\Modules\Finance\Models\FinanceCollectionSetting;
use App\Modules\Finance\Services\FinanceCollectionSettingService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class FinanceCollectionSettingController extends ApiController
{
    public function __construct(private readonly FinanceCollectionSettingService $service) {}

    public function show(): JsonResponse
    {
        $this->authorize('view', FinanceCollectionSetting::class);

        return $this->success(FinanceCollectionSettingResource::make($this->service->current()));
    }

    public function update(UpdateFinanceCollectionSettingRequest $request): JsonResponse
    {
        $this->authorize('update', FinanceCollectionSetting::class);

        $setting = $this->service->update($request->validated());

        return $this->success(FinanceCollectionSettingResource::make($setting), 'Régua de cobrança salva.');
    }
}
