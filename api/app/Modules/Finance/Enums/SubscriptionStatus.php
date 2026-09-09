<?php

namespace App\Modules\Finance\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativa',
            self::SUSPENDED => 'Suspensa',
            self::CANCELLED => 'Cancelada',
        };
    }
}
