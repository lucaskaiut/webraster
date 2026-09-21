<?php

namespace App\Modules\Vehicle\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Vehicle\Http\Requests\AssignEquipmentRequest;
use App\Modules\Vehicle\Http\Requests\StoreVehicleRequest;
use App\Modules\Vehicle\Http\Requests\UnassignEquipmentRequest;
use App\Modules\Vehicle\Http\Requests\UpdateVehicleRequest;
use App\Modules\Vehicle\Http\Resources\EquipmentAssignmentEventResource;
use App\Modules\Vehicle\Http\Resources\VehicleImageResource;
use App\Modules\Vehicle\Http\Resources\VehicleResource;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleImage;
use App\Modules\Vehicle\Services\EquipmentAssignmentService;
use App\Modules\Vehicle\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VehicleController extends ApiController
{
    public function __construct(
        private readonly VehicleService $service,
        private readonly EquipmentAssignmentService $assignments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $clientId = null;
        if ($request->filled('client_id')) {
            $clientId = Client::query()
                ->where('uuid', $request->string('client_id')->toString())
                ->value('id');
        }

        $vehicles = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
            $clientId,
        );

        return $this->paginated(VehicleResource::collection($vehicles));
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return $this->success(VehicleResource::make($vehicle->load(['client', 'equipment', 'images'])));
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $vehicle = $this->service->create($request->validated());

        return $this->created(VehicleResource::make($vehicle), 'Veículo criado com sucesso.');
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $vehicle = $this->service->update($vehicle, $request->validated());

        return $this->success(VehicleResource::make($vehicle), 'Veículo atualizado com sucesso.');
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        $this->service->delete($vehicle);

        return $this->success(null, 'Veículo removido com sucesso.');
    }

    public function storeImage(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $image = $vehicle->images()->create([
            'path' => $data['path'],
            'sort_order' => ((int) $vehicle->images()->max('sort_order')) + 1,
        ]);

        return $this->created(VehicleImageResource::make($image), 'Imagem adicionada com sucesso.');
    }

    public function destroyImage(Vehicle $vehicle, VehicleImage $image): JsonResponse
    {
        $this->authorize('update', $vehicle);

        abort_unless((int) $image->vehicle_id === (int) $vehicle->getKey(), 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return $this->success(null, 'Imagem removida com sucesso.');
    }

    public function installEquipment(AssignEquipmentRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $equipment = Equipment::query()->findOrFail($request->validated('equipment_id'));
        $event = $this->assignments->install(
            $vehicle->load('equipment'),
            $equipment,
            $request->validated('notes'),
            $request->validated('occurred_at'),
        );

        return $this->success(
            [
                'vehicle' => VehicleResource::make($vehicle->fresh()->load(['client', 'equipment'])),
                'event' => EquipmentAssignmentEventResource::make($event),
            ],
            'Equipamento instalado com sucesso.',
        );
    }

    public function removeEquipment(UnassignEquipmentRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $event = $this->assignments->remove(
            $vehicle->load('equipment'),
            $request->validated('notes'),
            $request->validated('occurred_at'),
        );

        return $this->success(
            [
                'vehicle' => VehicleResource::make($vehicle->fresh()->load(['client', 'equipment'])),
                'event' => EquipmentAssignmentEventResource::make($event),
            ],
            'Equipamento removido com sucesso.',
        );
    }

    public function swapEquipment(AssignEquipmentRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $equipment = Equipment::query()->findOrFail($request->validated('equipment_id'));
        $event = $this->assignments->swap(
            $vehicle->load('equipment'),
            $equipment,
            $request->validated('notes'),
            $request->validated('occurred_at'),
        );

        return $this->success(
            [
                'vehicle' => VehicleResource::make($vehicle->fresh()->load(['client', 'equipment'])),
                'event' => EquipmentAssignmentEventResource::make($event),
            ],
            'Equipamento trocado com sucesso.',
        );
    }

    public function assignmentHistory(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $events = $this->assignments->historyForVehicle($vehicle);

        return $this->success(EquipmentAssignmentEventResource::collection($events));
    }
}
