<?php

namespace App\Modules\Geofence\Support;

/**
 * Cálculos geográficos para geocercas.
 *
 * - Círculo: distância Haversine (metros) vs raio.
 * - Polígono: ray casting (point-in-polygon); pontos na aresta contam como dentro.
 * - Bounding box: pré-filtro antes do cálculo preciso.
 */
final class GeoMath
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    private const METERS_PER_DEGREE_LAT = 111320.0;

    /**
     * Distância em metros entre dois pontos (Haversine).
     */
    public static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($a)));
    }

    public static function isInsideCircle(
        float $latitude,
        float $longitude,
        float $centerLat,
        float $centerLng,
        int $radiusMeters,
    ): bool {
        return self::haversineMeters($latitude, $longitude, $centerLat, $centerLng) <= $radiusMeters;
    }

    /**
     * @param  list<array{latitude: float, longitude: float}>  $polygon
     */
    public static function isInsidePolygon(float $latitude, float $longitude, array $polygon): bool
    {
        if (count($polygon) < 3) {
            return false;
        }

        if (self::isOnPolygonEdge($latitude, $longitude, $polygon)) {
            return true;
        }

        $inside = false;
        $count = count($polygon);
        $j = $count - 1;

        for ($i = 0; $i < $count; $i++) {
            $yi = (float) $polygon[$i]['latitude'];
            $xi = (float) $polygon[$i]['longitude'];
            $yj = (float) $polygon[$j]['latitude'];
            $xj = (float) $polygon[$j]['longitude'];

            $intersects = (($yi > $latitude) !== ($yj > $latitude))
                && ($longitude < ($xj - $xi) * ($latitude - $yi) / (($yj - $yi) ?: 1e-15) + $xi);

            if ($intersects) {
                $inside = ! $inside;
            }

            $j = $i;
        }

        return $inside;
    }

    /**
     * @param  list<array{latitude: float, longitude: float}>  $polygon
     */
    public static function isOnPolygonEdge(float $latitude, float $longitude, array $polygon): bool
    {
        $count = count($polygon);
        $j = $count - 1;

        for ($i = 0; $i < $count; $i++) {
            if (self::pointOnSegment(
                $latitude,
                $longitude,
                (float) $polygon[$j]['latitude'],
                (float) $polygon[$j]['longitude'],
                (float) $polygon[$i]['latitude'],
                (float) $polygon[$i]['longitude'],
            )) {
                return true;
            }

            $j = $i;
        }

        return false;
    }

    public static function pointOnSegment(
        float $lat,
        float $lng,
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): bool {
        $cross = ($lng - $lng1) * ($lat2 - $lat1) - ($lat - $lat1) * ($lng2 - $lng1);

        if (abs($cross) > 1e-9) {
            return false;
        }

        $dot = ($lng - $lng1) * ($lng2 - $lng1) + ($lat - $lat1) * ($lat2 - $lat1);

        if ($dot < 0) {
            return false;
        }

        $lenSq = ($lng2 - $lng1) ** 2 + ($lat2 - $lat1) ** 2;

        return $dot <= $lenSq;
    }

    /**
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    public static function circleBoundingBox(float $centerLat, float $centerLng, int $radiusMeters): array
    {
        $deltaLat = $radiusMeters / self::METERS_PER_DEGREE_LAT;
        $cosLat = cos(deg2rad($centerLat));
        $metersPerDegreeLng = self::METERS_PER_DEGREE_LAT * max(0.01, abs($cosLat));
        $deltaLng = $radiusMeters / $metersPerDegreeLng;

        return [
            'min_lat' => $centerLat - $deltaLat,
            'max_lat' => $centerLat + $deltaLat,
            'min_lng' => $centerLng - $deltaLng,
            'max_lng' => $centerLng + $deltaLng,
        ];
    }

    /**
     * @param  list<array{latitude: float, longitude: float}>  $polygon
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    public static function polygonBoundingBox(array $polygon): array
    {
        $lats = array_map(fn (array $point) => (float) $point['latitude'], $polygon);
        $lngs = array_map(fn (array $point) => (float) $point['longitude'], $polygon);

        return [
            'min_lat' => min($lats),
            'max_lat' => max($lats),
            'min_lng' => min($lngs),
            'max_lng' => max($lngs),
        ];
    }

    public static function isInsideBoundingBox(
        float $latitude,
        float $longitude,
        float $minLat,
        float $maxLat,
        float $minLng,
        float $maxLng,
    ): bool {
        return $latitude >= $minLat
            && $latitude <= $maxLat
            && $longitude >= $minLng
            && $longitude <= $maxLng;
    }

    /**
     * Área aproximada em graus² (shoelace). Polígono degenerado ≈ 0.
     *
     * @param  list<array{latitude: float, longitude: float}>  $polygon
     */
    public static function polygonAreaAbsolute(array $polygon): float
    {
        $count = count($polygon);

        if ($count < 3) {
            return 0.0;
        }

        $sum = 0.0;
        $j = $count - 1;

        for ($i = 0; $i < $count; $i++) {
            $sum += ((float) $polygon[$j]['longitude'] + (float) $polygon[$i]['longitude'])
                * ((float) $polygon[$j]['latitude'] - (float) $polygon[$i]['latitude']);
            $j = $i;
        }

        return abs($sum / 2.0);
    }

    /**
     * @param  list<array{latitude: float|int|string, longitude: float|int|string}>  $points
     * @return list<array{latitude: float, longitude: float}>
     */
    public static function normalizePolygonPoints(array $points): array
    {
        $normalized = [];

        foreach ($points as $point) {
            if (! is_array($point)) {
                continue;
            }

            $lat = $point['latitude'] ?? $point['lat'] ?? null;
            $lng = $point['longitude'] ?? $point['lng'] ?? $point['lon'] ?? null;

            if ($lat === null || $lng === null) {
                continue;
            }

            $normalized[] = [
                'latitude' => round((float) $lat, 7),
                'longitude' => round((float) $lng, 7),
            ];
        }

        return $normalized;
    }
}
