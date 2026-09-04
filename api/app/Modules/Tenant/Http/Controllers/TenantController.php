<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Http\Requests\StoreChildTenantRequest;
use App\Modules\Tenant\Http\Requests\UpdateTenantRequest;
use App\Modules\Tenant\Http\Resources\TenantResource;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantService;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends ApiController
{
    public function __construct(private readonly TenantService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $umbrella = TenantContext::tenant();

        $children = $this->service->paginateChildren(
            $umbrella,
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(TenantResource::collection($children));
    }

    public function show(): JsonResponse
    {
        $tenant = TenantContext::tenant();

        $this->authorize('view', $tenant);

        return $this->success(TenantResource::make($tenant));
    }

    public function store(StoreChildTenantRequest $request): JsonResponse
    {
        $this->authorize('create', Tenant::class);

        $umbrella = TenantContext::tenant();

        $result = $this->service->createChild(
            $umbrella,
            $request->validated('tenant'),
            $request->validated('user'),
            $request->validated('plan_id'),
        );

        return $this->created([
            'tenant' => TenantResource::make($result['tenant']),
            'user' => UserResource::make($result['user']),
        ], 'Empresa criada com sucesso.');
    }

    public function update(UpdateTenantRequest $request): JsonResponse
    {
        $tenant = TenantContext::tenant();

        $this->authorize('update', $tenant);

        $tenant = $this->service->update($tenant, $request->validated());

        return $this->success(TenantResource::make($tenant), 'Tenant atualizado com sucesso.');
    }
}
