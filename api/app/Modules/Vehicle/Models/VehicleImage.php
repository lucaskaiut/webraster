<?php

namespace App\Modules\Vehicle\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\VehicleImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleImage extends Model
{
    /** @use HasFactory<VehicleImageFactory> */
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'vehicle_id',
        'path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    protected static function newFactory(): VehicleImageFactory
    {
        return VehicleImageFactory::new();
    }
}
