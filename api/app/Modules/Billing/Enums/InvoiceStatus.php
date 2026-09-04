<?php

namespace App\Modules\Billing\Enums;

enum InvoiceStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case PAID = 'PAID';
    case EXPIRED = 'EXPIRED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';

    public function isOpen(): bool
    {
        return match ($this) {
            self::PENDING, self::PROCESSING => true,
            default => false,
        };
    }
}
