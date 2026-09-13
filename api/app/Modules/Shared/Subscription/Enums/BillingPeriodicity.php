<?php

namespace App\Modules\Shared\Subscription\Enums;

enum BillingPeriodicity: string
{
    case MONTHLY = 'monthly';
    case BIMONTHLY = 'bimonthly';
    case QUARTERLY = 'quarterly';
    case SEMIANNUAL = 'semiannual';
    case ANNUAL = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Mensal',
            self::BIMONTHLY => 'Bimestral',
            self::QUARTERLY => 'Trimestral',
            self::SEMIANNUAL => 'Semestral',
            self::ANNUAL => 'Anual',
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::BIMONTHLY => 2,
            self::QUARTERLY => 3,
            self::SEMIANNUAL => 6,
            self::ANNUAL => 12,
        };
    }
}
