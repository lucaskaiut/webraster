<?php

namespace App\Modules\Client\Http\Controllers;

use App\Modules\Client\Http\Requests\StoreClientVehicleShareRequest;
use App\Modules\Client\Http\Resources\SharedVehicleResource;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Services\ClientVehicleShareService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientVehicleShareController extends ApiController
{
    public function __construct(private readonly ClientVehicleShareService $service) {}

    public function index(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $shares = $this->service->paginate($client, (int) $request->integer('per_page', 15));

        return $this->paginated(SharedVehicleResource::collection($shares));
    }

    public function store(StoreClientVehicleShareRequest $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $vehicle = Vehicle::query()
            ->where('uuid', $request->validated('vehicle_id'))
            ->firstOrFail();

        $share = $this->service->attach($client, $vehicle, $request->user());

        return $this->created(
            SharedVehicleResource::make($share),
            'Veículo compartilhado com o cliente.',
        );
    }

    public function destroy(Client $client, VehicleShare $share): JsonResponse
    {
        $this->authorize('update', $client);

        $this->service->detach($client, $share);

        return $this->success(null, 'Compartilhamento removido.');
    }
}
