<?php

namespace App\Modules\Geofence\Http\Controllers;

use App\Modules\Geofence\Http\Resources\GeofenceEventResource;
use App\Modules\Geofence\Models\GeofenceEvent;
use App\Modules\Geofence\Services\GeofenceEventService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofenceEventController extends ApiController
{
    public function __construct(private readonly GeofenceEventService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GeofenceEvent::class);

        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())
            : null;
        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to')->toString())
            : null;

        $events = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $this->service->resolveVehicleId($request->string('vehicle_id')->toString() ?: null),
            $this->service->resolveGeofenceId($request->string('geofence_id')->toString() ?: null),
            $this->service->resolveClientId($request->string('client_id')->toString() ?: null),
            $request->string('type')->toString() ?: null,
            $from,
            $to,
        );

        return $this->paginated(GeofenceEventResource::collection($events));
    }
}
