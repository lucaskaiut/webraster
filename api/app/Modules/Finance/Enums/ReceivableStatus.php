<?php

namespace App\Modules\Finance\Enums;

enum ReceivableStatus: string
{
    case PENDING = 'pending';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case RECEIVED = 'received';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::AWAITING_PAYMENT => 'Aguardando pagamento',
            self::RECEIVED => 'Recebido',
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
