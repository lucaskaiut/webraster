<?php

namespace App\Modules\DeviceCommand\Models;

use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Database\Factories\DeviceCommandLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommandLog extends Model
{
    /** @use HasFactory<DeviceCommandLogFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'vehicle_id',
        'equipment_id',
        'traccar_device_id',
        'user_id',
        'command_type',
        'payload',
        'status',
        'requested_at',
        'executed_at',
        'response',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'status' => DeviceCommandStatus::class,
            'requested_at' => 'datetime',
            'executed_at' => 'datetime',
            'traccar_device_id' => 'integer',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): DeviceCommandLogFactory
    {
        return DeviceCommandLogFactory::new();
    }
}
