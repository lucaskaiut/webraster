<?php

namespace App\Modules\Crm\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'crm_pipelines';

    protected $fillable = [
        'name',
        'description',
        'active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class, 'pipeline_id')->orderBy('position');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'pipeline_id');
    }
}
