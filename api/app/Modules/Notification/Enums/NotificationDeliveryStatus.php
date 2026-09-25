<?php

namespace App\Modules\Notification\Enums;

enum NotificationDeliveryStatus: string
{
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::SENT => 'Enviada',
            self::DELIVERED => 'Entregue',
            self::FAILED => 'Falhou',
        };
    }
}
