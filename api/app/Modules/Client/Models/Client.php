<?php

namespace App\Modules\Client\Models;

use App\Modules\Client\Models\Scopes\ClientScope;
use App\Modules\Driver\Models\Driver;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'document',
        'email',
        'phone',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'zip',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientScope);
    }

    /**
     * Portal do cliente: o próprio registro é filtrado pela PK.
     */
    public function clientScopeColumn(): string
    {
        return 'id';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }
}
