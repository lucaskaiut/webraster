<?php

namespace App\Modules\Alert\Enums;

enum AlertSeverity: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}
