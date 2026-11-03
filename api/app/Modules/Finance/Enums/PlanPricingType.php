<?php

namespace App\Modules\Finance\Enums;

enum PlanPricingType: string
{
    case FIXED = 'fixed';
    case PER_VEHICLE = 'per_vehicle';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Valor fixo',
            self::PER_VEHICLE => 'Por veículo',
        };
    }
}
