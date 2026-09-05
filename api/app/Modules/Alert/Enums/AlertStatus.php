<?php

namespace App\Modules\Alert\Enums;

enum AlertStatus: string
{
    case OPEN = 'open';
    case ACKNOWLEDGED = 'acknowledged';
    case RESOLVED = 'resolved';
}
