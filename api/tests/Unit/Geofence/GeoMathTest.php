<?php

namespace Tests\Unit\Geofence;

use App\Modules\Geofence\Support\GeoMath;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeoMathTest extends TestCase
{
    #[Test]
    public function circle_contains_center_and_rejects_far_point(): void
    {
        $centerLat = -25.4284;
        $centerLng = -49.2733;
        $radius = 500;

        $this->assertTrue(GeoMath::isInsideCircle($centerLat, $centerLng, $centerLat, $centerLng, $radius));
        $this->assertTrue(GeoMath::isInsideCircle(-25.4284, -49.2700, $centerLat, $centerLng, $radius));
        $this->assertFalse(GeoMath::isInsideCircle(-25.4500, -49.2733, $centerLat, $centerLng, $radius));
    }

    #[Test]
    public function circle_treats_exact_border_as_inside(): void
    {
        $centerLat = 0.0;
        $centerLng = 0.0;
        // ~111.32 m per 0.001 degree latitude near equator
        $borderLat = 0.0045;
        $distance = GeoMath::haversineMeters($centerLat, $centerLng, $borderLat, $centerLng);
        $radius = (int) ceil($distance);

        $this->assertTrue(GeoMath::isInsideCircle($borderLat, $centerLng, $centerLat, $centerLng, $radius));
        $this->assertFalse(GeoMath::isInsideCircle($borderLat, $centerLng, $centerLat, $centerLng, max(1, $radius - 10)));
    }

    #[Test]
    public function polygon_contains_interior_and_rejects_exterior(): void
    {
        $polygon = [
            ['latitude' => -25.4300, 'longitude' => -49.2750],
            ['latitude' => -25.4300, 'longitude' => -49.2700],
            ['latitude' => -25.4260, 'longitude' => -49.2700],
            ['latitude' => -25.4260, 'longitude' => -49.2750],
        ];

        $this->assertTrue(GeoMath::isInsidePolygon(-25.4280, -49.2725, $polygon));
        $this->assertFalse(GeoMath::isInsidePolygon(-25.4400, -49.2725, $polygon));
    }

    #[Test]
    public function polygon_treats_edge_as_inside(): void
    {
        $polygon = [
            ['latitude' => 0.0, 'longitude' => 0.0],
            ['latitude' => 0.0, 'longitude' => 1.0],
            ['latitude' => 1.0, 'longitude' => 1.0],
            ['latitude' => 1.0, 'longitude' => 0.0],
        ];

        $this->assertTrue(GeoMath::isInsidePolygon(0.0, 0.5, $polygon));
    }

    #[Test]
    public function bounding_box_prefilter_works(): void
    {
        $bbox = GeoMath::circleBoundingBox(-25.4284, -49.2733, 500);

        $this->assertTrue(GeoMath::isInsideBoundingBox(-25.4284, -49.2733, $bbox['min_lat'], $bbox['max_lat'], $bbox['min_lng'], $bbox['max_lng']));
        $this->assertFalse(GeoMath::isInsideBoundingBox(-25.5, -49.2733, $bbox['min_lat'], $bbox['max_lat'], $bbox['min_lng'], $bbox['max_lng']));
    }
}
