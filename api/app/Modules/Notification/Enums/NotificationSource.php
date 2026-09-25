<?php

namespace App\Modules\Notification\Enums;

enum NotificationSource: string
{
    case ALERT = 'alert';
    case FINANCE = 'finance';
    case MANUAL = 'manual';
    case SERVICE_ORDER = 'service_order';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::ALERT => 'Alerta',
            self::FINANCE => 'Financeiro',
            self::MANUAL => 'Manual',
            self::SERVICE_ORDER => 'Ordem de serviço',
            self::SYSTEM => 'Sistema',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
