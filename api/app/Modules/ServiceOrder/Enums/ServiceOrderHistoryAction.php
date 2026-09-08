<?php

namespace App\Modules\ServiceOrder\Enums;

enum ServiceOrderHistoryAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case STATUS_CHANGED = 'status_changed';
    case TECHNICIAN_CHANGED = 'technician_changed';
    case SCHEDULE_CHANGED = 'schedule_changed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
