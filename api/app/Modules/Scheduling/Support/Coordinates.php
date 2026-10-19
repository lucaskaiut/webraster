<?php

namespace App\Modules\Scheduling\Support;

final readonly class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    public static function tryFrom(mixed $latitude, mixed $longitude): ?self
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return new self((float) $latitude, (float) $longitude);
    }
}
