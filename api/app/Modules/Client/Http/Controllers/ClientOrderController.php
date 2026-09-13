<?php

namespace App\Modules\Client\Http\Controllers;

use App\Modules\Client\Http\Requests\UpsertClientOrderRequest;
use App\Modules\Client\Http\Resources\ClientOrderResource;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Services\ClientOrderService;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class ClientOrderController extends ApiController
{
    public function __construct(private readonly ClientOrderService $orders) {}

    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $order = $this->orders->currentForClient($client);

        return $this->success($order ? ClientOrderResource::make($order) : null);
    }

    public function upsert(UpsertClientOrderRequest $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);
        $this->authorize('create', FinanceSubscription::class);

        $order = $this->orders->upsert($client, $request->validated());

        return $this->success(ClientOrderResource::make($order), 'Pedido salvo com sucesso.');
    }
}
