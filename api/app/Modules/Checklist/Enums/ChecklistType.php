<?php

namespace App\Modules\Checklist\Enums;

enum ChecklistType: string
{
    case INITIAL = 'initial';
    case FINAL = 'final';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL => 'Inicial',
            self::FINAL => 'Final',
        };
    }
}
