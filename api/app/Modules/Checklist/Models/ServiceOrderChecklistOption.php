<?php

namespace App\Modules\Checklist\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderChecklistOption extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'service_order_checklist_question_id',
        'checklist_question_option_id',
        'label',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderChecklistQuestion::class, 'service_order_checklist_question_id');
    }
}
