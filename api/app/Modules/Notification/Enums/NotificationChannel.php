<?php

namespace App\Modules\Notification\Enums;

enum NotificationChannel: string
{
    case IN_APP = 'in_app';
    case PUSH = 'push';
    case EMAIL = 'email';

    public function label(): string
    {
        return match ($this) {
            self::IN_APP => 'Aplicativo',
            self::PUSH => 'Push',
            self::EMAIL => 'E-mail',
        };
    }
}
