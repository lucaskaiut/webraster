<?php

namespace App\Modules\Client\Enums;

enum ContractSignatureStatus: string
{
    case PENDING = 'pending';
    case SIGNED = 'signed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::SIGNED => 'Assinado',
        };
    }

    public function isSigned(): bool
    {
        return $this === self::SIGNED;
    }
}
