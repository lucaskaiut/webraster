<?php

namespace App\Modules\Client\Http\Controllers;

use App\Modules\Client\Http\Requests\StoreClientRequest;
use App\Modules\Client\Http\Requests\UpdateClientRequest;
use App\Modules\Client\Http\Resources\ClientResource;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Services\ClientService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends ApiController
{
    public function __construct(private readonly ClientService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $clients = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(ClientResource::collection($clients));
    }

    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $client->loadMissing('plan');

        return $this->success(ClientResource::make($client));
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $this->authorize('create', Client::class);

        $client = $this->service->create($request->validated());

        return $this->created(ClientResource::make($client), 'Cliente criado com sucesso.');
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $client = $this->service->update($client, $request->validated());

        return $this->success(ClientResource::make($client), 'Cliente atualizado com sucesso.');
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        $this->service->delete($client);

        return $this->success(null, 'Cliente removido com sucesso.');
    }
}
