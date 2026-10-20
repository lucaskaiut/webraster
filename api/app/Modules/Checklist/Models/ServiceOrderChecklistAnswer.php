<?php

namespace App\Modules\Checklist\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceOrderChecklistAnswer extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'service_order_checklist_question_id',
        'text_answer',
        'answered_at',
        'answered_by',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderChecklistQuestion::class, 'service_order_checklist_question_id');
    }

    public function selectedOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceOrderChecklistOption::class,
            'service_order_checklist_answer_options',
            'answer_id',
            'option_id',
        )->withTimestamps();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklistPhoto::class, 'answer_id')->orderBy('id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}
