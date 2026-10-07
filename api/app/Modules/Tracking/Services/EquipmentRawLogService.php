<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\EquipmentRawLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EquipmentRawLogService
{
    private const MAX_LINES_PER_EQUIPMENT = 2_000;

    public function appendFromPositionAttributes(Equipment $equipment, array $attributes, ?CarbonInterface $deviceTime = null): void
    {
        // Traccar: Position.KEY_ORIGINAL → attributes.raw (texto ou hex:… em protocolos binários).
        if (! isset($attributes['raw']) || ! is_string($attributes['raw'])) {
            return;
        }

        $line = trim($attributes['raw']);

        if ($line === '') {
            return;
        }

        $last = EquipmentRawLog::query()
            ->where('equipment_id', $equipment->getKey())
            ->orderByDesc('id')
            ->value('line');

        if ($last === $line) {
            return;
        }

        EquipmentRawLog::query()->create([
            'tenant_id' => $equipment->tenant_id,
            'equipment_id' => $equipment->getKey(),
            'imei' => (string) $equipment->imei,
            'line' => $line,
            'device_time' => $deviceTime,
            'received_at' => now(),
        ]);

        $this->prune($equipment);
    }

    /**
     * @return Collection<int, EquipmentRawLog>
     */
    public function tail(Equipment $equipment, int $afterId = 0, int $limit = 300): Collection
    {
        $limit = min(500, max(1, $limit));

        if ($afterId > 0) {
            return EquipmentRawLog::query()
                ->where('equipment_id', $equipment->getKey())
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($limit)
                ->get();
        }

        $ids = EquipmentRawLog::query()
            ->where('equipment_id', $equipment->getKey())
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return EquipmentRawLog::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();
    }

    private function prune(Equipment $equipment): void
    {
        $keepFromId = EquipmentRawLog::query()
            ->where('equipment_id', $equipment->getKey())
            ->orderByDesc('id')
            ->skip(self::MAX_LINES_PER_EQUIPMENT)
            ->value('id');

        if ($keepFromId === null) {
            return;
        }

        EquipmentRawLog::query()
            ->where('equipment_id', $equipment->getKey())
            ->where('id', '<=', $keepFromId)
            ->delete();
    }
}
