<?php

namespace App\Modules\Chat\Enums;

enum MessageDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}
