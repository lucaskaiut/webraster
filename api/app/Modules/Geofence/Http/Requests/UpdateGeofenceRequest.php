<?php

namespace App\Modules\Geofence\Http\Requests;

use App\Modules\Client\Models\Client;
use App\Modules\Geofence\Enums\GeofenceType;
use App\Modules\Geofence\Services\GeofenceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeofenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['sometimes', Rule::enum(GeofenceType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'center_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'center_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => [
                'nullable',
                'integer',
                'min:1',
                'max:'.GeofenceService::MAX_RADIUS_METERS,
            ],
            'geometry' => ['nullable', 'array', 'min:'.GeofenceService::MIN_POLYGON_POINTS, 'max:'.GeofenceService::MAX_POLYGON_POINTS],
            'geometry.*.latitude' => ['required_with:geometry', 'numeric', 'between:-90,90'],
            'geometry.*.longitude' => ['required_with:geometry', 'numeric', 'between:-180,180'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->filled('client_id') && ! is_numeric($this->input('client_id'))) {
            $client = Client::query()->where('uuid', $this->input('client_id'))->first();
            $input['client_id'] = $client?->getKey();
        }

        if ($this->has('geometry') && is_array($this->input('geometry'))) {
            $input['geometry'] = array_map(function ($point) {
                if (! is_array($point)) {
                    return $point;
                }

                return [
                    'latitude' => $point['latitude'] ?? $point['lat'] ?? null,
                    'longitude' => $point['longitude'] ?? $point['lng'] ?? $point['lon'] ?? null,
                ];
            }, $this->input('geometry'));
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }
}
