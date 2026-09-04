<?php

namespace App\Modules\ACL\Http\Controllers;

use App\Modules\ACL\Http\Requests\StoreRoleRequest;
use App\Modules\ACL\Http\Requests\UpdateRoleRequest;
use App\Modules\ACL\Http\Resources\RoleResource;
use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends ApiController
{
    public function __construct(private readonly RoleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = $this->service->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(RoleResource::collection($roles));
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return $this->success(RoleResource::make($role->load('permissions')));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->service->create($request->validated());

        return $this->created(RoleResource::make($role), 'Role criada com sucesso.');
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role = $this->service->update($role, $request->validated());

        return $this->success(RoleResource::make($role), 'Role atualizada com sucesso.');
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        $this->service->delete($role);

        return $this->success(null, 'Role removida com sucesso.');
    }
}
