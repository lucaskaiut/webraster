<?php

namespace App\Modules\Assistant\Enums;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
