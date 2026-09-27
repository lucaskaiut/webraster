<?php

namespace App\Modules\Crm\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PipelineStage extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'crm_pipeline_stages';

    protected $fillable = [
        'pipeline_id',
        'name',
        'description',
        'position',
        'color',
        'is_initial',
        'is_final',
        'is_won',
        'is_lost',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_initial' => 'boolean',
            'is_final' => 'boolean',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'stage_id');
    }

    public function aiConfiguration(): HasOne
    {
        return $this->hasOne(AiStageConfiguration::class, 'stage_id');
    }
}
