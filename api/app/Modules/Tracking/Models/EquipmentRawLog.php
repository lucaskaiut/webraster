<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentRawLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'equipment_id',
        'imei',
        'line',
        'device_time',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'device_time' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
