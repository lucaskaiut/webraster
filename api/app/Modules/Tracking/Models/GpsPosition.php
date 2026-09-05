<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsPosition extends Model
{
    use BelongsToClient;
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'vehicle_id',
        'client_id',
        'equipment_id',
        'latitude',
        'longitude',
        'recorded_at',
        'speed',
        'ignition',
        'battery',
        'heading',
        'altitude',
        'traccar_position_id',
        'attributes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'recorded_at' => 'datetime',
            'speed' => 'float',
            'ignition' => 'boolean',
            'battery' => 'float',
            'heading' => 'float',
            'altitude' => 'float',
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
}
