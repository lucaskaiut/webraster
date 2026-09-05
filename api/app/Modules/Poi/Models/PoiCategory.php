<?php

namespace App\Modules\Poi\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\PoiCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoiCategory extends Model
{
    /** @use HasFactory<PoiCategoryFactory> */
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function pois(): HasMany
    {
        return $this->hasMany(Poi::class);
    }

    protected static function newFactory(): PoiCategoryFactory
    {
        return PoiCategoryFactory::new();
    }
}
