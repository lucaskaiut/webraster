<?php

namespace App\Modules\Geofence\Enums;

enum GeofenceType: string
{
    case CIRCLE = 'circle';
    case POLYGON = 'polygon';
}
