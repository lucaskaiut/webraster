<?php

namespace App\Modules\Shared\Subscription\Enums;

enum BillingStatus: string
{
    case PENDING = 'pending';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::AWAITING_PAYMENT => 'Aguardando pagamento',
            self::PAID => 'Pago',
            self::OVERDUE => 'Vencido',
            self::CANCELLED => 'Cancelado',
            self::REFUNDED => 'Estornado',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::PENDING, self::AWAITING_PAYMENT, self::OVERDUE], true);
    }
}
