<?php

namespace App\Modules\Checklist\Models;

use App\Modules\Checklist\Enums\ChecklistAnswerType;
use App\Modules\Checklist\Enums\ChecklistPhotoRule;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceOrderChecklistQuestion extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'service_order_checklist_id',
        'checklist_question_id',
        'prompt',
        'answer_type',
        'photo_rule',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'answer_type' => ChecklistAnswerType::class,
            'photo_rule' => ChecklistPhotoRule::class,
            'position' => 'integer',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderChecklist::class, 'service_order_checklist_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklistOption::class)->orderBy('position')->orderBy('id');
    }

    public function answer(): HasOne
    {
        return $this->hasOne(ServiceOrderChecklistAnswer::class);
    }
}
