<?php

namespace App\Modules\Alert\Http\Controllers;

use App\Modules\Alert\Http\Resources\AlertResource;
use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Services\AlertService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends ApiController
{
    public function __construct(private readonly AlertService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Alert::class);

        $vehicleId = null;
        if ($request->filled('vehicle_id')) {
            $vehicleId = Vehicle::query()
                ->where('uuid', $request->string('vehicle_id')->toString())
                ->value('id');
        }

        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())
            : null;
        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to')->toString())
            : null;

        $alerts = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $vehicleId,
            $request->string('type')->toString() ?: null,
            $request->string('status')->toString() ?: null,
            $request->string('severity')->toString() ?: null,
            $from,
            $to,
            $request->string('sort')->toString() === 'asc' ? 'asc' : 'desc',
        );

        return $this->paginated(AlertResource::collection($alerts));
    }

    public function map(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Alert::class);

        $status = $request->has('status')
            ? ($request->string('status')->toString() ?: null)
            : 'open';

        return $this->success(AlertResource::collection($this->service->listForMap($status)));
    }

    public function dashboard(): JsonResponse
    {
        $this->authorize('viewAny', Alert::class);

        $stats = $this->service->dashboardStats();
        $stats['critical_open'] = AlertResource::collection($stats['critical_open']);

        return $this->success($stats);
    }

    public function show(Alert $alert): JsonResponse
    {
        $this->authorize('view', $alert);

        return $this->success(AlertResource::make($alert->load(['vehicle', 'equipment', 'client'])));
    }

    public function acknowledge(Request $request, Alert $alert): JsonResponse
    {
        $this->authorize('manage', $alert);

        $alert = $this->service->acknowledge($alert, $request->user());

        return $this->success(AlertResource::make($alert), 'Alerta reconhecido.');
    }

    public function resolve(Request $request, Alert $alert): JsonResponse
    {
        $this->authorize('manage', $alert);

        $alert = $this->service->resolve($alert, $request->user());

        return $this->success(AlertResource::make($alert), 'Alerta resolvido.');
    }
}
