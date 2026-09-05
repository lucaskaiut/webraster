<?php

namespace App\Modules\Geofence\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\Geofence\Http\Requests\StoreGeofenceRequest;
use App\Modules\Geofence\Http\Requests\UpdateGeofenceRequest;
use App\Modules\Geofence\Http\Resources\GeofenceResource;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Geofence\Services\GeofenceService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofenceController extends ApiController
{
    public function __construct(private readonly GeofenceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Geofence::class);

        $clientId = null;
        if ($request->filled('client_id')) {
            $clientId = Client::query()
                ->where('uuid', $request->string('client_id')->toString())
                ->value('id');
        }

        $isActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : null;

        $geofences = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
            $clientId,
            $request->string('type')->toString() ?: null,
            $isActive,
        );

        return $this->paginated(GeofenceResource::collection($geofences));
    }

    public function map(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Geofence::class);

        $clientId = null;
        if ($request->filled('client_id')) {
            $clientId = Client::query()
                ->where('uuid', $request->string('client_id')->toString())
                ->value('id');
        }

        $activeOnly = ! $request->has('is_active') || $request->boolean('is_active');

        $geofences = $this->service->listForMap($clientId, $activeOnly);

        return $this->success(GeofenceResource::collection($geofences));
    }

    public function show(Geofence $geofence): JsonResponse
    {
        $this->authorize('view', $geofence);

        return $this->success(GeofenceResource::make($geofence->load(['client'])->loadCount('events')));
    }

    public function store(StoreGeofenceRequest $request): JsonResponse
    {
        $this->authorize('create', Geofence::class);

        $geofence = $this->service->create($request->validated());

        return $this->created(GeofenceResource::make($geofence), 'Geocerca criada com sucesso.');
    }

    public function update(UpdateGeofenceRequest $request, Geofence $geofence): JsonResponse
    {
        $this->authorize('update', $geofence);

        $geofence = $this->service->update($geofence, $request->validated());

        return $this->success(GeofenceResource::make($geofence), 'Geocerca atualizada com sucesso.');
    }

    public function destroy(Geofence $geofence): JsonResponse
    {
        $this->authorize('delete', $geofence);

        $this->service->delete($geofence);

        return $this->success(null, 'Geocerca removida com sucesso.');
    }
}
