<?php

namespace App\Modules\Crm\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiStageConfiguration extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'crm_ai_stage_configurations';

    protected $fillable = [
        'tenant_id',
        'stage_id',
        'enabled',
        'objective',
        'instructions',
        'success_criteria',
        'allowed_actions',
        'restricted_actions',
        'temperature',
        'model',
        'max_tokens',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'allowed_actions' => 'array',
            'restricted_actions' => 'array',
            'temperature' => 'float',
            'max_tokens' => 'integer',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }
}
