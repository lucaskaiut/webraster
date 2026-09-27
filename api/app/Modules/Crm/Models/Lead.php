<?php

namespace App\Modules\Crm\Models;

use App\Modules\Chat\Models\Contact;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Crm\Enums\LeadStatus;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'crm_leads';

    protected $fillable = [
        'contact_id',
        'pipeline_id',
        'stage_id',
        'owner_user_id',
        'status',
        'source',
        'score',
        'notes',
        'ai_enabled',
        'last_interaction_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'ai_enabled' => 'boolean',
            'last_interaction_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'lead_id');
    }
}
