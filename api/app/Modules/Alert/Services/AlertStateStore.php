<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertState;
use App\Modules\Tracking\Models\GpsPosition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AlertStateStore
{
    public static function scopeIdForDeviceAlarm(string $alarmCode): int
    {
        $hash = crc32(strtolower(trim($alarmCode))) & 0x7FFFFFFF;

        return $hash === 0 ? -1 : -$hash;
    }

    public function get(int $tenantId, int $vehicleId, AlertType $type, ?int $alertConfigId = null): AlertState
    {
        $scopeId = $alertConfigId ?? 0;

        return AlertState::query()
            ->withoutGlobalScopes()
            ->firstOrNew(
                [
                    'tenant_id' => $tenantId,
                    'vehicle_id' => $vehicleId,
                    'type' => $type->value,
                    'alert_config_id' => $scopeId,
                ],
                [
                    'is_active' => false,
                ],
            );
    }

    /**
     * @return Collection<int, AlertState>
     */
    public function activeForVehicle(int $vehicleId, AlertType $type): Collection
    {
        return AlertState::query()
            ->withoutGlobalScopes()
            ->where('vehicle_id', $vehicleId)
            ->where('type', $type->value)
            ->where('is_active', true)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function activate(
        AlertState $state,
        GpsPosition $position,
        array $meta = [],
        ?CarbonImmutable $startedAt = null,
    ): AlertState {
        $state->forceFill([
            'tenant_id' => $position->tenant_id,
            'is_active' => true,
            'started_at' => $startedAt ?? ($state->started_at ?? $position->recorded_at),
            'last_gps_position_id' => $position->getKey(),
            'last_recorded_at' => $position->recorded_at,
            'meta' => array_merge(is_array($state->meta) ? $state->meta : [], $meta),
        ])->save();

        return $state;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function touch(AlertState $state, GpsPosition $position, array $meta = []): AlertState
    {
        $state->forceFill([
            'tenant_id' => $position->tenant_id,
            'last_gps_position_id' => $position->getKey(),
            'last_recorded_at' => $position->recorded_at,
            'meta' => array_merge(is_array($state->meta) ? $state->meta : [], $meta),
        ])->save();

        return $state;
    }

    public function deactivate(AlertState $state, ?GpsPosition $position = null): AlertState
    {
        $state->forceFill([
            'is_active' => false,
            'started_at' => null,
            'last_gps_position_id' => $position?->getKey() ?? $state->last_gps_position_id,
            'last_recorded_at' => $position?->recorded_at ?? $state->last_recorded_at,
            'meta' => null,
        ])->save();

        return $state;
    }
}
