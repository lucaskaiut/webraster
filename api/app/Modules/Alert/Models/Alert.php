<?php

namespace App\Modules\Alert\Models;

use App\Modules\Alert\Enums\AlertSeverity;
use App\Modules\Alert\Enums\AlertStatus;
use App\Modules\Alert\Enums\AlertType;
use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use BelongsToClient;
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'client_id',
        'vehicle_id',
        'equipment_id',
        'gps_position_id',
        'type',
        'severity',
        'status',
        'title',
        'description',
        'latitude',
        'longitude',
        'speed',
        'meta',
        'occurred_at',
        'acknowledged_at',
        'resolved_at',
        'acknowledged_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'severity' => AlertSeverity::class,
            'status' => AlertStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'speed' => 'float',
            'meta' => 'array',
            'occurred_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function gpsPosition(): BelongsTo
    {
        return $this->belongsTo(GpsPosition::class);
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
