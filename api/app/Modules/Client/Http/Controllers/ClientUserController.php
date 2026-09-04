<?php

namespace App\Modules\Client\Http\Controllers;

use App\Modules\Client\Http\Requests\StoreClientUserRequest;
use App\Modules\Client\Http\Requests\UpdateClientUserRequest;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Services\ClientUserService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientUserController extends ApiController
{
    public function __construct(private readonly ClientUserService $service) {}

    public function index(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $users = $this->service->paginate(
            $client,
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(UserResource::collection($users));
    }

    public function store(StoreClientUserRequest $request, Client $client): JsonResponse
    {
        $this->authorize('manageUsers', $client);

        $user = $this->service->create($client, $request->validated());

        return $this->created(UserResource::make($user), 'Usuário do cliente criado com sucesso.');
    }

    public function update(UpdateClientUserRequest $request, Client $client, User $user): JsonResponse
    {
        $this->authorize('manageUsers', $client);

        $user = $this->service->update($client, $user, $request->validated());

        return $this->success(UserResource::make($user), 'Usuário do cliente atualizado com sucesso.');
    }

    public function destroy(Client $client, User $user): JsonResponse
    {
        $this->authorize('manageUsers', $client);

        $this->service->delete($client, $user);

        return $this->success(null, 'Usuário do cliente removido com sucesso.');
    }
}
