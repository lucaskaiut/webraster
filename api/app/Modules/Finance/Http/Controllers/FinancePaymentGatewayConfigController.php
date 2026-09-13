<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Http\Requests\UpdatePaymentGatewayConfigRequest;
use App\Modules\Finance\Models\TenantPaymentGatewayConfig;
use App\Modules\Finance\Services\PaymentGatewayConfigService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancePaymentGatewayConfigController extends ApiController
{
    public function __construct(private readonly PaymentGatewayConfigService $service) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TenantPaymentGatewayConfig::class);

        $gateway = $request->string('gateway')->toString() ?: null;

        return $this->success($this->service->show($gateway));
    }

    public function update(UpdatePaymentGatewayConfigRequest $request): JsonResponse
    {
        $this->authorize('update', TenantPaymentGatewayConfig::class);

        return $this->success($this->service->upsert($request->validated()), 'Configuração do gateway salva.');
    }
}
