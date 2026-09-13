<?php

namespace App\Modules\VehicleData\DTOs;

/**
 * Representação normalizada dos dados veiculares, independente do provedor.
 * Campos ausentes no provedor são retornados como null.
 */
final readonly class VehicleDataDTO
{
    /**
     * @param  array<string, mixed>|null  $extra
     */
    public function __construct(
        public string $plate,
        public ?string $brand,
        public ?string $model,
        public ?string $submodel,
        public ?string $version,
        public ?int $year,
        public ?int $modelYear,
        public ?string $color,
        public ?string $chassis,
        public ?string $fuel,
        public ?string $transmission,
        public ?string $segment,
        public ?string $municipality,
        public ?string $uf,
        public ?string $situation,
        public ?VehicleFipeDTO $fipe,
        public ?array $extra,
    ) {}
}
