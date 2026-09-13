<?php

namespace App\Modules\Finance\Enums;

enum PaymentMethod: string
{
    case PIX = 'pix';
    case BOLETO = 'boleto';
    case CREDIT_CARD = 'credit_card';

    public function label(): string
    {
        return match ($this) {
            self::PIX => 'PIX',
            self::BOLETO => 'Boleto',
            self::CREDIT_CARD => 'Cartão de crédito',
        };
    }
}
