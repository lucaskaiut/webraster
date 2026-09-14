<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Vehicle\Models\Vehicle;
use Database\Factories\GpsPositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsPosition extends Model
{
    /** @use HasFactory<GpsPositionFactory> */
    use BelongsToClient;

    use BelongsToTenant;
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'vehicle_id',
        'client_id',
        'equipment_id',
        'latitude',
        'longitude',
        'recorded_at',
        'server_time',
        'speed',
        'ignition',
        'battery',
        'heading',
        'altitude',
        'address',
        'valid',
        'traccar_position_id',
        'attributes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'recorded_at' => 'datetime',
            'server_time' => 'datetime',
            'speed' => 'float',
            'ignition' => 'boolean',
            'battery' => 'float',
            'heading' => 'float',
            'altitude' => 'float',
            'valid' => 'boolean',
            'attributes' => 'array',
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

    protected static function newFactory(): GpsPositionFactory
    {
        return GpsPositionFactory::new();
    }
}
