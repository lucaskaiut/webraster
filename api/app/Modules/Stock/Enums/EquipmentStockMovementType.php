<?php

namespace App\Modules\Stock\Enums;

enum EquipmentStockMovementType: string
{
    case ENTRY = 'entry';
    case TRANSFER = 'transfer';
    case EXIT = 'exit';
    case INSTALLATION = 'installation';
    case RETURN = 'return';

    public function label(): string
    {
        return match ($this) {
            self::ENTRY => 'Entrada',
            self::TRANSFER => 'Transferência',
            self::EXIT => 'Saída',
            self::INSTALLATION => 'Instalação',
            self::RETURN => 'Retorno',
        };
    }
}
