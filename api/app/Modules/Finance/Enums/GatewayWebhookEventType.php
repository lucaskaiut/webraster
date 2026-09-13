<?php

namespace App\Modules\Finance\Enums;

enum GatewayWebhookEventType: string
{
    case PAYMENT_PAID = 'payment_paid';
    case PAYMENT_OVERDUE = 'payment_overdue';
    case PAYMENT_DELETED = 'payment_deleted';
    case PAYMENT_REFUNDED = 'payment_refunded';
    case IGNORED = 'ignored';
}
