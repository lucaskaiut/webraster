<?php

namespace App\Modules\Checklist\Models;

use App\Modules\Checklist\Enums\ChecklistType;
use App\Modules\Checklist\Enums\ServiceOrderChecklistStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceOrderChecklist extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'service_order_id',
        'checklist_id',
        'type',
        'name',
        'description',
        'status',
        'completed_at',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChecklistType::class,
            'status' => ServiceOrderChecklistStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklistQuestion::class)->orderBy('position')->orderBy('id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
