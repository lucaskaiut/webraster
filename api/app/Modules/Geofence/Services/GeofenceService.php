<?php

namespace App\Modules\Geofence\Services;

use App\Modules\Geofence\Enums\GeofenceType;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Geofence\Support\GeoMath;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class GeofenceService
{
    public const MAX_RADIUS_METERS = 100_000;

    public const MIN_POLYGON_POINTS = 3;

    public const MAX_POLYGON_POINTS = 100;

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $clientId = null,
        ?string $type = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator {
        return Geofence::query()
            ->with(['client'])
            ->withCount('events')
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Geofence>
     */
    public function listForMap(?int $clientId = null, bool $activeOnly = true)
    {
        return Geofence::query()
            ->with(['client'])
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->orderBy('name')
            ->limit(500)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Geofence
    {
        return Geofence::query()->create($this->payload($data))->load(['client']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Geofence $geofence, array $data): Geofence
    {
        $geofence->fill($this->payload($data, $geofence));
        $geofence->save();

        return $geofence->refresh()->load(['client']);
    }

    public function delete(Geofence $geofence): void
    {
        $geofence->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?Geofence $existing = null): array
    {
        $type = isset($data['type'])
            ? GeofenceType::from((string) $data['type'])
            : ($existing?->type ?? GeofenceType::CIRCLE);

        $payload = Arr::only($data, [
            'client_id',
            'name',
            'description',
            'type',
            'is_active',
            'center_latitude',
            'center_longitude',
            'radius_meters',
            'geometry',
        ]);

        if ($type === GeofenceType::CIRCLE) {
            $centerLat = (float) ($payload['center_latitude'] ?? $existing?->center_latitude);
            $centerLng = (float) ($payload['center_longitude'] ?? $existing?->center_longitude);
            $radius = (int) ($payload['radius_meters'] ?? $existing?->radius_meters);

            if ($radius <= 0 || $radius > self::MAX_RADIUS_METERS) {
                throw ValidationException::withMessages([
                    'radius_meters' => ['O raio deve ser maior que zero e no máximo '.self::MAX_RADIUS_METERS.' metros.'],
                ]);
            }

            $payload['center_latitude'] = $centerLat;
            $payload['center_longitude'] = $centerLng;
            $payload['radius_meters'] = $radius;
            $payload['geometry'] = null;

            $bbox = GeoMath::circleBoundingBox($centerLat, $centerLng, $radius);
        } else {
            $points = GeoMath::normalizePolygonPoints($payload['geometry'] ?? $existing?->geometry ?? []);

            if (count($points) < self::MIN_POLYGON_POINTS) {
                throw ValidationException::withMessages([
                    'geometry' => ['O polígono deve ter pelo menos '.self::MIN_POLYGON_POINTS.' pontos.'],
                ]);
            }

            if (count($points) > self::MAX_POLYGON_POINTS) {
                throw ValidationException::withMessages([
                    'geometry' => ['O polígono pode ter no máximo '.self::MAX_POLYGON_POINTS.' pontos.'],
                ]);
            }

            if (GeoMath::polygonAreaAbsolute($points) < 1e-12) {
                throw ValidationException::withMessages([
                    'geometry' => ['O polígono é inválido ou degenerado.'],
                ]);
            }

            $payload['geometry'] = $points;
            $payload['center_latitude'] = null;
            $payload['center_longitude'] = null;
            $payload['radius_meters'] = null;

            $bbox = GeoMath::polygonBoundingBox($points);
        }

        $payload['bbox_min_lat'] = $bbox['min_lat'];
        $payload['bbox_max_lat'] = $bbox['max_lat'];
        $payload['bbox_min_lng'] = $bbox['min_lng'];
        $payload['bbox_max_lng'] = $bbox['max_lng'];

        return $payload;
    }
}
