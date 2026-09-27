<?php

namespace App\Modules\Chat\Models;

use App\Modules\Crm\Models\Lead;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'chat_contacts';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'avatar_url',
        'external_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'contact_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'contact_id');
    }
}
