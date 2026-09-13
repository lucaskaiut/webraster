<?php

namespace App\Modules\Vehicle\Enums;

enum VehicleTransmission: string
{
    case MANUAL = 'manual';
    case AUTOMATIC = 'automatic';
    case AUTOMATED = 'automated';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::AUTOMATIC => 'Automático',
            self::AUTOMATED => 'Automatizado',
        };
    }
}
