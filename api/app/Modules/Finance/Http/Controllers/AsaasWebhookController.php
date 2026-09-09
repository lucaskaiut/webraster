<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Services\AsaasWebhookProcessor;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsaasWebhookController extends ApiController
{
    public function __construct(private readonly AsaasWebhookProcessor $processor) {}

    public function __invoke(Request $request, string $tenantUuid): JsonResponse
    {
        $tenant = Tenant::query()->where('uuid', $tenantUuid)->firstOrFail();

        $accessToken = $request->header('asaas-access-token')
            ?? $request->header('Asaas-Access-Token')
            ?? $request->query('token');

        $this->processor->process(
            $tenant,
            $request->all(),
            is_string($accessToken) ? $accessToken : null,
        );

        return $this->success(['ok' => true]);
    }
}
