<?php

namespace App\Modules\Poi\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\PoiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poi extends Model
{
    /** @use HasFactory<PoiFactory> */
    use BelongsToClient;
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'poi_category_id',
        'name',
        'description',
        'latitude',
        'longitude',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PoiCategory::class, 'poi_category_id');
    }

    protected static function newFactory(): PoiFactory
    {
        return PoiFactory::new();
    }
}
