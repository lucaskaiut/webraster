<?php

namespace App\Modules\VehicleData\DTOs;

/**
 * Valor FIPE (melhor correspondência por score) de um veículo.
 */
final readonly class VehicleFipeDTO
{
    public function __construct(
        public ?string $code,
        public ?string $modelYear,
        public ?string $fuel,
        public ?string $referenceMonth,
        public ?string $value,
        public ?string $model,
        public ?string $brand,
        public ?int $score,
    ) {}
}
