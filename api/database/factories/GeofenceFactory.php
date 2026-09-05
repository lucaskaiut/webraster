<?php

namespace Database\Factories;

use App\Modules\Client\Models\Client;
use App\Modules\Geofence\Enums\GeofenceType;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Geofence\Support\GeoMath;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Geofence>
 */
class GeofenceFactory extends Factory
{
    protected $model = Geofence::class;

    public function definition(): array
    {
        $lat = (float) fake()->latitude(-30, -20);
        $lng = (float) fake()->longitude(-55, -45);
        $radius = 500;
        $bbox = GeoMath::circleBoundingBox($lat, $lng, $radius);

        return [
            'tenant_id' => Tenant::factory(),
            'client_id' => Client::factory(),
            'name' => 'Geocerca '.fake()->unique()->word(),
            'description' => fake()->optional()->sentence(),
            'type' => GeofenceType::CIRCLE,
            'is_active' => true,
            'center_latitude' => $lat,
            'center_longitude' => $lng,
            'radius_meters' => $radius,
            'geometry' => null,
            'bbox_min_lat' => $bbox['min_lat'],
            'bbox_max_lat' => $bbox['max_lat'],
            'bbox_min_lng' => $bbox['min_lng'],
            'bbox_max_lng' => $bbox['max_lng'],
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->getKey(),
        ]);
    }

    public function circle(float $lat, float $lng, int $radiusMeters = 500): static
    {
        $bbox = GeoMath::circleBoundingBox($lat, $lng, $radiusMeters);

        return $this->state(fn (): array => [
            'type' => GeofenceType::CIRCLE,
            'center_latitude' => $lat,
            'center_longitude' => $lng,
            'radius_meters' => $radiusMeters,
            'geometry' => null,
            'bbox_min_lat' => $bbox['min_lat'],
            'bbox_max_lat' => $bbox['max_lat'],
            'bbox_min_lng' => $bbox['min_lng'],
            'bbox_max_lng' => $bbox['max_lng'],
        ]);
    }

    /**
     * @param  list<array{latitude: float, longitude: float}>  $points
     */
    public function polygon(array $points): static
    {
        $normalized = GeoMath::normalizePolygonPoints($points);
        $bbox = GeoMath::polygonBoundingBox($normalized);

        return $this->state(fn (): array => [
            'type' => GeofenceType::POLYGON,
            'center_latitude' => null,
            'center_longitude' => null,
            'radius_meters' => null,
            'geometry' => $normalized,
            'bbox_min_lat' => $bbox['min_lat'],
            'bbox_max_lat' => $bbox['max_lat'],
            'bbox_min_lng' => $bbox['min_lng'],
            'bbox_max_lng' => $bbox['max_lng'],
        ]);
    }
}
