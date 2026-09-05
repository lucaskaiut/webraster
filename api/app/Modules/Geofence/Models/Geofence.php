<?php

namespace App\Modules\Geofence\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Geofence\Enums\GeofenceType;
use App\Modules\Geofence\Support\GeoMath;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\GeofenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Geofence extends Model
{
    /** @use HasFactory<GeofenceFactory> */
    use BelongsToClient;
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'name',
        'description',
        'type',
        'is_active',
        'center_latitude',
        'center_longitude',
        'radius_meters',
        'geometry',
        'bbox_min_lat',
        'bbox_max_lat',
        'bbox_min_lng',
        'bbox_max_lng',
    ];

    protected function casts(): array
    {
        return [
            'type' => GeofenceType::class,
            'is_active' => 'boolean',
            'center_latitude' => 'float',
            'center_longitude' => 'float',
            'radius_meters' => 'integer',
            'geometry' => 'array',
            'bbox_min_lat' => 'float',
            'bbox_max_lat' => 'float',
            'bbox_min_lng' => 'float',
            'bbox_max_lng' => 'float',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(GeofenceEvent::class);
    }

    public function contains(float $latitude, float $longitude): bool
    {
        if (
            $this->bbox_min_lat !== null
            && $this->bbox_max_lat !== null
            && $this->bbox_min_lng !== null
            && $this->bbox_max_lng !== null
            && ! GeoMath::isInsideBoundingBox(
                $latitude,
                $longitude,
                $this->bbox_min_lat,
                $this->bbox_max_lat,
                $this->bbox_min_lng,
                $this->bbox_max_lng,
            )
        ) {
            return false;
        }

        return match ($this->type) {
            GeofenceType::CIRCLE => GeoMath::isInsideCircle(
                $latitude,
                $longitude,
                (float) $this->center_latitude,
                (float) $this->center_longitude,
                (int) $this->radius_meters,
            ),
            GeofenceType::POLYGON => GeoMath::isInsidePolygon(
                $latitude,
                $longitude,
                is_array($this->geometry) ? $this->geometry : [],
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{bbox_min_lat: float, bbox_max_lat: float, bbox_min_lng: float, bbox_max_lng: float}|null
     */
    public static function computeBoundingBox(GeofenceType|string $type, array $attributes): ?array
    {
        $type = $type instanceof GeofenceType ? $type : GeofenceType::from($type);

        if ($type === GeofenceType::CIRCLE) {
            return GeoMath::circleBoundingBox(
                (float) $attributes['center_latitude'],
                (float) $attributes['center_longitude'],
                (int) $attributes['radius_meters'],
            );
        }

        $points = GeoMath::normalizePolygonPoints($attributes['geometry'] ?? []);

        if ($points === []) {
            return null;
        }

        return GeoMath::polygonBoundingBox($points);
    }

    protected static function newFactory(): GeofenceFactory
    {
        return GeofenceFactory::new();
    }
}
