<?php

namespace App\Modules\Scheduling\Support;

/**
 * Estimativa de um trecho (ida). O agendamento aplica o mesmo trecho na
 * chegada e na saída, totalizando ida + volta.
 */
final readonly class TravelEstimate
{
    public const SOURCE_OSRM = 'osrm';

    public const SOURCE_ESTIMATE = 'estimate';

    /**
     * @param  list<array{0: float, 1: float}>  $geometry  Pontos [latitude, longitude] do trajeto.
     */
    public function __construct(
        public int $minutes,
        public int $distanceMeters,
        public string $source,
        public array $geometry = [],
    ) {}

    /**
     * @return array{minutes: int, distance_meters: int, source: string}
     */
    public function toArray(): array
    {
        return [
            'minutes' => $this->minutes,
            'distance_meters' => $this->distanceMeters,
            'source' => $this->source,
        ];
    }
}
