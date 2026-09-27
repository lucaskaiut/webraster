<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Services\ChatWebhookService;
use App\Modules\Chat\Support\MessagingGatewayResolver;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EvolutionWebhookController extends ApiController
{
    public function __construct(
        private readonly ChatWebhookService $webhooks,
        private readonly MessagingGatewayResolver $gateways,
    ) {}

    public function __invoke(Request $request, \App\Modules\Chat\Models\MessagingConnection $webhookConnection): JsonResponse
    {
        if (! $webhookConnection->is_active) {
            abort(404);
        }

        TenantContext::set($webhookConnection->tenant);

        $gateway = $this->gateways->resolve($webhookConnection->provider);

        if (! $gateway->authenticateWebhook($webhookConnection, $request)) {
            throw ValidationException::withMessages([
                'webhook' => ['Webhook não autorizado.'],
            ]);
        }

        $this->webhooks->ingest($webhookConnection, $request);

        return $this->success(['ok' => true]);
    }
}
