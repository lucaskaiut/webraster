<?php

namespace App\Modules\Chat\Enums;

enum WebhookEventStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::PROCESSED, self::FAILED], true);
    }
}
