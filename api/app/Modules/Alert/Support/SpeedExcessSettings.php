<?php

namespace App\Modules\Alert\Support;

use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Vehicle\Models\Vehicle;

final class SpeedExcessSettings
{
    public function __construct(
        public readonly float $limitKmh,
        public readonly float $hysteresisPercent,
        public readonly int $minDurationSeconds,
    ) {}

    public static function forVehicle(Vehicle $vehicle, ?AlertConfig $config = null): ?self
    {
        $configSettings = $config?->settingsWithDefaults() ?? [];

        $limit = $vehicle->max_speed_kmh ?? ($configSettings['speed_limit_kmh'] ?? null);

        if ($limit === null || (float) $limit <= 0) {
            return null;
        }

        return new self(
            limitKmh: (float) $limit,
            hysteresisPercent: (float) ($vehicle->speed_hysteresis_percent ?? 3),
            minDurationSeconds: (int) ($vehicle->speed_min_duration_seconds
                ?? $configSettings['min_duration_seconds']
                ?? 30),
        );
    }

    public function openThreshold(): float
    {
        return round($this->limitKmh * (1 + ($this->hysteresisPercent / 100)), 2);
    }

    public function closeThreshold(): float
    {
        return round($this->limitKmh * (1 - ($this->hysteresisPercent / 100)), 2);
    }
}
