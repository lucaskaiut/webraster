<?php

namespace App\Modules\Commission\Enums;

use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;

enum CommissionStatus: string
{
    case FORECAST = 'forecast';
    case FINALIZED = 'finalized';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::FORECAST => 'Prevista',
            self::FINALIZED => 'Finalizada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public static function fromOrderStatus(ServiceOrderStatus $status): self
    {
        return match ($status) {
            ServiceOrderStatus::OPEN, ServiceOrderStatus::IN_PROGRESS => self::FORECAST,
            ServiceOrderStatus::COMPLETED => self::FINALIZED,
            ServiceOrderStatus::CANCELLED => self::CANCELLED,
        };
    }

    /**
     * @return list<self>
     */
    public static function visible(): array
    {
        return [self::FORECAST, self::FINALIZED];
    }
}
