<?php

namespace App\Modules\Service\Http\Controllers;

use App\Modules\Service\Http\Requests\StoreServiceRequest;
use App\Modules\Service\Http\Requests\UpdateServiceRequest;
use App\Modules\Service\Http\Resources\ServiceResource;
use App\Modules\Service\Models\Service;
use App\Modules\Service\Services\ServiceService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends ApiController
{
    public function __construct(private readonly ServiceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $services = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(ServiceResource::collection($services));
    }

    public function show(Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        return $this->success(ServiceResource::make($service));
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $this->authorize('create', Service::class);

        $service = $this->service->create($request->validated());

        return $this->created(ServiceResource::make($service), 'Serviço criado com sucesso.');
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $service = $this->service->update($service, $request->validated());

        return $this->success(ServiceResource::make($service), 'Serviço atualizado com sucesso.');
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $this->service->delete($service);

        return $this->success(null, 'Serviço removido com sucesso.');
    }
}
