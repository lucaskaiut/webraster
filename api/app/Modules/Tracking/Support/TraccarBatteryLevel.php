<?php

namespace App\Modules\Tracking\Support;

final class TraccarBatteryLevel
{
    private const POWER_PROTOCOLS = ['easytrack'];

    public static function powerMeansBattery(?string $protocol): bool
    {
        return in_array(strtolower((string) $protocol), self::POWER_PROTOCOLS, true);
    }

    public static function fromPower(?string $protocol, float $power): ?float
    {
        if (! self::powerMeansBattery($protocol)) {
            return null;
        }

        if ($power < 0 || $power > 100) {
            return null;
        }

        if ($power < 30 && fmod($power, 1.0) !== 0.0) {
            return null;
        }

        return $power;
    }
}
