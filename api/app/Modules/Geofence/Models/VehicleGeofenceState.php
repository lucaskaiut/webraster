<?php

namespace App\Modules\Geofence\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleGeofenceState extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'vehicle_id',
        'geofence_id',
        'is_inside',
        'last_gps_position_id',
        'last_recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_inside' => 'boolean',
            'last_recorded_at' => 'datetime',
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

    public function lastGpsPosition(): BelongsTo
    {
        return $this->belongsTo(GpsPosition::class, 'last_gps_position_id');
    }
}
