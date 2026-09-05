<?php

namespace App\Modules\Geofence\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Geofence\Enums\GeofenceEventType;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofenceEvent extends Model
{
    use BelongsToClient;
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'client_id',
        'vehicle_id',
        'geofence_id',
        'gps_position_id',
        'type',
        'latitude',
        'longitude',
        'recorded_at',
        'processed_at',
        'speed',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => GeofenceEventType::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'recorded_at' => 'datetime',
            'processed_at' => 'datetime',
            'speed' => 'float',
            'meta' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    public function gpsPosition(): BelongsTo
    {
        return $this->belongsTo(GpsPosition::class);
    }
}
