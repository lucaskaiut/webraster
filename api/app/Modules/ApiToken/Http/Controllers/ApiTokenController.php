<?php

namespace App\Modules\ApiToken\Http\Controllers;

use App\Modules\ApiToken\Http\Requests\StoreApiTokenRequest;
use App\Modules\ApiToken\Http\Resources\ApiTokenResource;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\ApiToken\Services\ApiTokenService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ApiTokenController extends ApiController
{
    public function __construct(private readonly ApiTokenService $service) {}

    public function index(): JsonResponse
    {
        return $this->success(ApiTokenResource::collection($this->service->list()));
    }

    public function store(StoreApiTokenRequest $request): JsonResponse
    {
        $expiresAt = $request->validated('expires_at');
        $permissions = $request->validated('permissions');

        $issued = $this->service->issue(
            $request->validated('name'),
            $expiresAt !== null ? Carbon::parse($expiresAt) : null,
            $permissions !== null ? $permissions : null,
        );

        return $this->created([
            'token' => $issued->plainTextToken,
            'api_token' => ApiTokenResource::make($issued->apiToken),
        ], 'Token criado com sucesso. Guarde-o em local seguro: ele não será exibido novamente.');
    }

    public function destroy(ApiToken $apiToken): JsonResponse
    {
        $this->service->revoke($apiToken);

        return $this->success(null, 'Token revogado com sucesso.');
    }
}
