<?php

namespace App\Modules\Checklist\Enums;

enum ServiceOrderChecklistStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::COMPLETED => 'Concluído',
        };
    }
}
