<?php

namespace App\Modules\Vehicle\Models;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Vehicle\Enums\AssignmentEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentAssignmentEvent extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'vehicle_id',
        'equipment_id',
        'previous_equipment_id',
        'event',
        'occurred_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event' => AssignmentEventType::class,
            'occurred_at' => 'datetime',
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

    public function previousEquipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'previous_equipment_id');
    }
}
