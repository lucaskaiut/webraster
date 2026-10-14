<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Dashboard\Services\MonitoringDashboardService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringDashboardController extends ApiController
{
    public function __construct(private readonly MonitoringDashboardService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $allowed = $user?->hasPermission(Permission::TRACKING_READ)
            || $user?->hasPermission(Permission::VEHICLE_READ)
            || $user?->hasPermission(Permission::EQUIPMENT_READ);

        abort_unless((bool) $allowed, 403);

        return $this->success($this->service->metrics());
    }
}
