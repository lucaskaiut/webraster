<?php

namespace App\Modules\Finance\Enums;

enum GatewayPaymentStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';
    case EXPIRED = 'EXPIRED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    case REFUNDED = 'REFUNDED';

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }
}
