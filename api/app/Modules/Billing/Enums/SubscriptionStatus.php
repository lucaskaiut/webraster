<?php

namespace App\Modules\Billing\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case TRIALING = 'TRIALING';
    case PAST_DUE = 'PAST_DUE';
    case SUSPENDED = 'SUSPENDED';
    case CANCELLED = 'CANCELLED';

    public function allowsAccess(): bool
    {
        return match ($this) {
            self::ACTIVE, self::TRIALING => true,
            default => false,
        };
    }
}
