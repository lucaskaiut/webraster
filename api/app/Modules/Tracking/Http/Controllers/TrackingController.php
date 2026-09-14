<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\Tracking\Http\Requests\TrackingHistoryRequest;
use App\Modules\Tracking\Http\Resources\GpsPositionResource;
use App\Modules\Tracking\Http\Resources\LiveTrackingItemResource;
use App\Modules\Tracking\Services\TrackingService;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends ApiController
{
    public function __construct(private readonly TrackingService $service) {}

    public function status(): JsonResponse
    {
        $this->authorizeTracking();

        return $this->success($this->service->gatewayStatus());
    }

    public function live(Request $request): JsonResponse
    {
        $this->authorizeTracking();

        $items = $this->service->live(
            $request->string('search')->toString() ?: null,
        );

        $payload = $items->map(
            fn (array $item) => new LiveTrackingItemResource(
                $item['vehicle'],
                $item['position'],
                $item['online'],
            ),
        );

        return $this->success($payload);
    }

    public function history(TrackingHistoryRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeTracking();
        $this->authorizeVehicle($vehicle);

        $positions = $this->service->history(
            $vehicle,
            CarbonImmutable::parse($request->validated('from')),
            CarbonImmutable::parse($request->validated('to')),
        );

        return $this->success(GpsPositionResource::collection($positions));
    }

    private function authorizeTracking(): void
    {
        abort_unless(
            request()->user()?->hasPermission(Permission::TRACKING_READ) ?? false,
            403,
        );
    }

    private function authorizeVehicle(Vehicle $vehicle): void
    {
        abort_unless(
            TenantAuthorization::matchesCurrentTenant((int) $vehicle->tenant_id)
            && ClientAuthorization::allowsClient((int) $vehicle->client_id),
            403,
        );
    }
}
