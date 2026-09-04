<?php

namespace App\Modules\Driver\Http\Controllers;

use App\Modules\Driver\Http\Requests\StoreDriverRequest;
use App\Modules\Driver\Http\Requests\UpdateDriverRequest;
use App\Modules\Driver\Http\Resources\DriverResource;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Services\DriverService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends ApiController
{
    public function __construct(private readonly DriverService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Driver::class);

        $clientId = null;
        if ($request->filled('client_id')) {
            $clientId = \App\Modules\Client\Models\Client::query()
                ->where('uuid', $request->string('client_id')->toString())
                ->value('id');
        }

        $drivers = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
            $clientId,
        );

        return $this->paginated(DriverResource::collection($drivers));
    }

    public function show(Driver $driver): JsonResponse
    {
        $this->authorize('view', $driver);

        return $this->success(DriverResource::make($driver->load('client')));
    }

    public function store(StoreDriverRequest $request): JsonResponse
    {
        $this->authorize('create', Driver::class);

        $driver = $this->service->create($request->validated());

        return $this->created(DriverResource::make($driver), 'Motorista criado com sucesso.');
    }

    public function update(UpdateDriverRequest $request, Driver $driver): JsonResponse
    {
        $this->authorize('update', $driver);

        $driver = $this->service->update($driver, $request->validated());

        return $this->success(DriverResource::make($driver), 'Motorista atualizado com sucesso.');
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $this->authorize('delete', $driver);

        $this->service->delete($driver);

        return $this->success(null, 'Motorista removido com sucesso.');
    }
}
