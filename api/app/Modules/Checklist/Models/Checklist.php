<?php

namespace App\Modules\Checklist\Models;

use App\Modules\Checklist\Enums\ChecklistType;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\ChecklistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checklist extends Model
{
    /** @use HasFactory<ChecklistFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChecklistType::class,
            'is_active' => 'boolean',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ChecklistQuestion::class)->orderBy('position')->orderBy('id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklist::class);
    }

    protected static function newFactory(): ChecklistFactory
    {
        return ChecklistFactory::new();
    }
}
