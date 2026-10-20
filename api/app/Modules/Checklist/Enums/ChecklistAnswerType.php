<?php

namespace App\Modules\Checklist\Enums;

enum ChecklistAnswerType: string
{
    case TEXT = 'text';
    case SINGLE_CHOICE = 'single_choice';
    case MULTIPLE_CHOICE = 'multiple_choice';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Texto',
            self::SINGLE_CHOICE => 'Seleção única',
            self::MULTIPLE_CHOICE => 'Múltipla seleção',
        };
    }

    public function requiresOptions(): bool
    {
        return $this !== self::TEXT;
    }
}
