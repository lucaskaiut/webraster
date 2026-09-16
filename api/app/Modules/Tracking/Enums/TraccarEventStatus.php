<?php

namespace App\Modules\Tracking\Enums;

enum TraccarEventStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case IGNORED = 'ignored';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::PROCESSED, self::IGNORED, self::FAILED => true,
            default => false,
        };
    }
}
