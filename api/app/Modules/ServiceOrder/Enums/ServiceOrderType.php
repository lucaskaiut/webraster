<?php

namespace App\Modules\ServiceOrder\Enums;

enum ServiceOrderType: string
{
    case INSTALLATION = 'installation';
    case MAINTENANCE = 'maintenance';
    case REMOVAL = 'removal';

    public function label(): string
    {
        return match ($this) {
            self::INSTALLATION => 'Instalação',
            self::MAINTENANCE => 'Manutenção',
            self::REMOVAL => 'Retirada',
        };
    }

    /**
     * @return list<self>
     */
    public static function values(): array
    {
        return self::cases();
    }
}
