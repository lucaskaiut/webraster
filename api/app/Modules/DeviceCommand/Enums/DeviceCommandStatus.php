<?php

namespace App\Modules\DeviceCommand\Enums;

enum DeviceCommandStatus: string
{
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
