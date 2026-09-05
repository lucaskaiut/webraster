<?php

namespace App\Modules\Equipment\Http\Controllers;

use App\Modules\Equipment\Http\Requests\StoreEquipmentRequest;
use App\Modules\Equipment\Http\Requests\UpdateEquipmentRequest;
use App\Modules\Equipment\Http\Resources\EquipmentResource;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Equipment\Services\EquipmentService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Vehicle\Http\Resources\EquipmentAssignmentEventResource;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Services\EquipmentAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentController extends ApiController
{
    public function __construct(
        private readonly EquipmentService $service,
        private readonly EquipmentAssignmentService $assignments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Equipment::class);

        $vehicleId = null;
        if ($request->filled('vehicle_id')) {
            $vehicleId = Vehicle::query()
                ->where('uuid', $request->string('vehicle_id')->toString())
                ->value('id');
        }

        $availableOnly = $request->has('available')
            ? $request->boolean('available')
            : null;

        $equipments = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
            $availableOnly,
            $vehicleId,
        );

        return $this->paginated(EquipmentResource::collection($equipments));
    }

    public function show(Equipment $equipment): JsonResponse
    {
        $this->authorize('view', $equipment);

        return $this->success(EquipmentResource::make($equipment->load('vehicle.client')));
    }

    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $this->authorize('create', Equipment::class);

        $equipment = $this->service->create($request->validated());

        return $this->created(EquipmentResource::make($equipment), 'Equipamento criado com sucesso.');
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);

        $equipment = $this->service->update($equipment, $request->validated());

        return $this->success(EquipmentResource::make($equipment), 'Equipamento atualizado com sucesso.');
    }

    public function destroy(Equipment $equipment): JsonResponse
    {
        $this->authorize('delete', $equipment);

        $this->service->delete($equipment);

        return $this->success(null, 'Equipamento removido com sucesso.');
    }

    public function assignmentHistory(Equipment $equipment): JsonResponse
    {
        $this->authorize('view', $equipment);

        $events = $this->assignments->historyForEquipment($equipment);

        return $this->success(EquipmentAssignmentEventResource::collection($events));
    }
}
