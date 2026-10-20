<?php

namespace App\Modules\Commission\Http\Controllers;

use App\Modules\Commission\Http\Resources\CommissionResource;
use App\Modules\Commission\Models\Commission;
use App\Modules\Commission\Services\CommissionService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionController extends ApiController
{
    public function __construct(private readonly CommissionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Commission::class);

        $technicianId = null;

        if ($request->filled('technician_id')) {
            $technicianId = User::query()
                ->where('uuid', $request->string('technician_id')->toString())
                ->value('id');
        }

        $commissions = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            [
                'search' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'technician_id' => $technicianId,
                'sort' => $request->string('sort')->toString() ?: null,
            ],
            $request->user(),
        );

        return $this->paginated(CommissionResource::collection($commissions));
    }
}
