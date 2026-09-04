<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Support\PaymentGatewayResolver;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends ApiController
{
    public function __construct(
        private readonly PaymentGatewayResolver $gateways,
    ) {}

    /**
     * Métodos de pagamento ativos (públicos, para checkout).
     */
    public function index(): JsonResponse
    {
        $methods = array_map(
            fn (array $item): array => [
                'id' => $item['key'],
                'name' => $item['label'],
                'payment_method' => $item['payment_method'],
            ],
            $this->gateways->catalog(),
        );

        return $this->success($methods);
    }
}
