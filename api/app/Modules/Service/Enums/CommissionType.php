<?php

namespace App\Modules\Service\Enums;

enum CommissionType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Percentual',
            self::FIXED => 'Valor fixo',
        };
    }
}
