<?php

namespace App\Modules\Shared\Subscription\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativa',
            self::PAST_DUE => 'Em atraso',
            self::SUSPENDED => 'Suspensa',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function allowsAccess(): bool
    {
        return match ($this) {
            self::ACTIVE, self::PAST_DUE => true,
            default => false,
        };
    }
}
