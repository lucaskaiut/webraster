<?php

namespace App\Modules\Chat\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessagingConnection extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'chat_messaging_connections';

    protected $fillable = [
        'provider',
        'name',
        'base_url',
        'credentials',
        'instance_name',
        'connection_status',
        'capabilities',
        'is_active',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'capabilities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'connection_id');
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        $credentials = is_array($this->credentials) ? $this->credentials : [];

        return $credentials[$key] ?? $default;
    }

    public function webhookUrl(): string
    {
        return url("/api/webhooks/messaging/evolution/{$this->uuid}");
    }
}
