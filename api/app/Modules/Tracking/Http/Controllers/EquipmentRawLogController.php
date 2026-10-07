<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tracking\Services\EquipmentRawLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentRawLogController extends ApiController
{
    public function __construct(private readonly EquipmentRawLogService $rawLogs) {}

    public function __invoke(Equipment $equipment, Request $request): JsonResponse
    {
        $this->authorize('view', $equipment);

        abort_unless(
            (bool) $request->user()?->hasPermission(Permission::EQUIPMENT_DETAILS_READ),
            403,
            'Sem permissão para visualizar logs brutos do equipamento.',
        );

        $afterId = max(0, (int) $request->integer('after_id', 0));
        $limit = (int) $request->integer('limit', 300);

        $lines = $this->rawLogs->tail($equipment, $afterId, $limit);

        $formatted = $lines->map(fn ($row) => [
            'id' => $row->id,
            'line' => $row->line,
            'imei' => $row->imei,
            'device_time' => $row->device_time?->toIso8601String(),
            'received_at' => $row->received_at->toIso8601String(),
        ])->values();

        $lastId = $afterId;
        if ($formatted->isNotEmpty()) {
            $lastId = (int) $formatted->last()['id'];
        }

        return $this->success([
            'lines' => $formatted,
            'last_id' => $lastId,
        ]);
    }
}
