<?php

namespace App\Modules\Alert\Support;

final class SpeedConverter
{
    private const KNOTS_TO_KMH = 1.852;

    public static function knotsToKmh(?float $knots): ?float
    {
        if ($knots === null || is_nan($knots)) {
            return null;
        }

        return round($knots * self::KNOTS_TO_KMH, 1);
    }
}
