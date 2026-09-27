<?php

namespace App\Modules\Crm\Models;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolExecution extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'crm_ai_tool_executions';

    protected $fillable = [
        'conversation_id',
        'lead_id',
        'tool',
        'arguments',
        'result',
        'status',
        'error',
        'execution_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result' => 'array',
            'execution_time_ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
