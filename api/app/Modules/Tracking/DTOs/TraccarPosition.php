<?php

namespace App\Modules\Tracking\DTOs;

use Carbon\CarbonImmutable;

readonly class TraccarPosition
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public int $id,
        public int $deviceId,
        public float $latitude,
        public float $longitude,
        public CarbonImmutable $recordedAt,
        public ?float $speed,
        public ?bool $ignition,
        public ?float $battery,
        public ?float $heading,
        public ?float $altitude,
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var array<string, mixed> $attributes */
        $attributes = is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [];

        $ignition = null;
        if (array_key_exists('ignition', $attributes)) {
            $ignition = (bool) $attributes['ignition'];
        }

        $battery = null;
        if (isset($attributes['battery'])) {
            $battery = (float) $attributes['battery'];
        } elseif (isset($attributes['batteryLevel'])) {
            $battery = (float) $attributes['batteryLevel'];
        }

        return new self(
            id: (int) $payload['id'],
            deviceId: (int) $payload['deviceId'],
            latitude: (float) $payload['latitude'],
            longitude: (float) $payload['longitude'],
            recordedAt: CarbonImmutable::parse((string) $payload['deviceTime']),
            speed: isset($payload['speed']) ? (float) $payload['speed'] : null,
            ignition: $ignition,
            battery: $battery,
            heading: isset($payload['course']) ? (float) $payload['course'] : null,
            altitude: isset($payload['altitude']) ? (float) $payload['altitude'] : null,
            attributes: self::normalizeAttributes($attributes, $payload),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function normalizeAttributes(array $attributes, array $payload): array
    {
        if (isset($payload['protocol']) && $payload['protocol'] !== '') {
            $attributes['protocol'] = (string) $payload['protocol'];
        }

        return $attributes;
    }
}
