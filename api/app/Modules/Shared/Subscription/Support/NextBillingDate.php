<?php

namespace App\Modules\Shared\Subscription\Support;

use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use Carbon\CarbonImmutable;

final class NextBillingDate
{
    public static function advance(CarbonImmutable $from, BillingPeriodicity $periodicity): CarbonImmutable
    {
        return $from->addMonthsNoOverflow($periodicity->months());
    }
}
