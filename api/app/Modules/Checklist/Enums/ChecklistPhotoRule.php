<?php

namespace App\Modules\Checklist\Enums;

enum ChecklistPhotoRule: string
{
    case REQUIRED = 'required';
    case OPTIONAL = 'optional';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::REQUIRED => 'Obrigatória',
            self::OPTIONAL => 'Opcional',
            self::NONE => 'Não se aplica',
        };
    }
}
