<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Services\PaymentWebhookProcessor;
use App\Modules\Finance\Support\PaymentGatewayResolver;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentWebhookController extends ApiController
{
    public function __construct(
        private readonly PaymentGatewayResolver $gateways,
        private readonly PaymentWebhookProcessor $processor,
    ) {}

    public function __invoke(Request $request, string $gateway, string $tenantUuid): JsonResponse
    {
        $tenant = Tenant::query()->where('uuid', $tenantUuid)->firstOrFail();
        TenantContext::set($tenant);

        $implementation = $this->gateways->resolve($gateway);

        if (! $implementation->authenticateWebhook($request)) {
            throw ValidationException::withMessages([
                'payment_gateway' => ['Token de webhook inválido.'],
            ]);
        }

        $this->processor->process(
            $tenant,
            $implementation,
            $implementation->parseWebhook($request),
        );

        return $this->success(['ok' => true]);
    }
}
