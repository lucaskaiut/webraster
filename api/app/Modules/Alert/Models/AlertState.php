<?php

namespace App\Modules\Alert\Models;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertState extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'vehicle_id',
        'alert_config_id',
        'type',
        'is_active',
        'started_at',
        'last_gps_position_id',
        'last_recorded_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'alert_config_id' => 'integer',
            'is_active' => 'boolean',
            'started_at' => 'datetime',
            'last_recorded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function lastGpsPosition(): BelongsTo
    {
        return $this->belongsTo(GpsPosition::class, 'last_gps_position_id');
    }
}
