<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Http\Requests\EvolutionBootstrapRequest;
use App\Modules\Chat\Http\Requests\StoreMessagingConnectionRequest;
use App\Modules\Chat\Http\Requests\UpdateMessagingConnectionRequest;
use App\Modules\Chat\Http\Resources\EvolutionConnectResource;
use App\Modules\Chat\Http\Resources\MessagingConnectionResource;
use App\Modules\Chat\Models\MessagingConnection;
use App\Modules\Chat\Services\EvolutionInstanceService;
use App\Modules\Chat\Services\MessagingConnectionService;
use App\Modules\Chat\Support\MessagingGatewayResolver;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class MessagingConnectionController extends ApiController
{
    public function __construct(
        private readonly MessagingConnectionService $service,
        private readonly MessagingGatewayResolver $gateways,
        private readonly EvolutionInstanceService $evolutionInstances,
    ) {}

    public function providers(): JsonResponse
    {
        $this->authorize('viewAny', MessagingConnection::class);

        return $this->success($this->gateways->catalog());
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', MessagingConnection::class);

        return $this->success(
            MessagingConnectionResource::collection($this->service->list()),
        );
    }

    public function store(StoreMessagingConnectionRequest $request): JsonResponse
    {
        $this->authorize('create', MessagingConnection::class);

        $connection = $this->service->create($request->validated());

        return $this->created(MessagingConnectionResource::make($connection));
    }

    public function update(UpdateMessagingConnectionRequest $request, MessagingConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $connection = $this->service->update($connection, $request->validated());

        return $this->success(MessagingConnectionResource::make($connection));
    }

    public function evolutionBootstrap(EvolutionBootstrapRequest $request): JsonResponse
    {
        $this->authorize('create', MessagingConnection::class);

        $result = $this->evolutionInstances->bootstrap($request->validated());

        return $this->created([
            'connection' => MessagingConnectionResource::make($result['connection']),
            'connect' => EvolutionConnectResource::make($result['connect']),
        ]);
    }

    public function evolutionConnect(MessagingConnection $messagingConnection): JsonResponse
    {
        $this->authorize('update', $messagingConnection);

        $this->evolutionInstances->provisionInstance($messagingConnection);

        $connect = $this->evolutionInstances->connect($messagingConnection);

        return $this->success([
            'connection' => MessagingConnectionResource::make($messagingConnection->fresh()),
            'connect' => EvolutionConnectResource::make($connect),
        ]);
    }

    public function evolutionState(MessagingConnection $messagingConnection): JsonResponse
    {
        $this->authorize('update', $messagingConnection);

        $connect = $this->evolutionInstances->syncState($messagingConnection);

        return $this->success([
            'connection' => MessagingConnectionResource::make($messagingConnection->fresh()),
            'connect' => EvolutionConnectResource::make($connect),
        ]);
    }
}
