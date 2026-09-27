<?php

namespace App\Modules\Chat\Enums;

enum SenderType: string
{
    case CONTACT = 'contact';
    case USER = 'user';
    case AI = 'ai';
    case SYSTEM = 'system';
}
