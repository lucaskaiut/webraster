<?php

namespace App\Modules\Stock\Http\Controllers;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Stock\Http\Resources\EquipmentStockMovementResource;
use App\Modules\Stock\Services\EquipmentStockMovementService;
use Illuminate\Http\JsonResponse;

class EquipmentStockMovementController extends ApiController
{
    public function __construct(private readonly EquipmentStockMovementService $movements) {}

    public function index(Equipment $equipment): JsonResponse
    {
        $this->authorize('view', $equipment);

        return $this->success(
            EquipmentStockMovementResource::collection(
                $this->movements->historyForEquipment($equipment),
            ),
        );
    }
}
