<?php

namespace App\Modules\Geofence\Enums;

enum GeofenceEventType: string
{
    case ENTRY = 'entry';
    case EXIT = 'exit';
}
