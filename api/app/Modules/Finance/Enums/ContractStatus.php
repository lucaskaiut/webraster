<?php

namespace App\Modules\Finance\Enums;

enum ContractStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativo',
            self::SUSPENDED => 'Suspenso',
            self::CANCELLED => 'Cancelado',
        };
    }
}
