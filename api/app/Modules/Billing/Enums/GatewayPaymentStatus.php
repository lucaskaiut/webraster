<?php

namespace App\Modules\Billing\Enums;

enum GatewayPaymentStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case PAID = 'PAID';
    case EXPIRED = 'EXPIRED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}
