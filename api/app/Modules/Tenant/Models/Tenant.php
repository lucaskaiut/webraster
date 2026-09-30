<?php

namespace App\Modules\Tenant\Models;

use App\Modules\ACL\Models\Role;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\User\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasUuid;

    protected $fillable = [
        'parent_id',
        'name',
        'identifier',
        'document',
        'email',
        'phone',
        'logo_path',
        'favicon_path',
        'signature_path',
        'vehicle_alert_defaults',
        'app_name',
        'app_icon_path',
        'app_logo_path',
        'app_primary_color',
        'app_secondary_color',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_alert_defaults' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (blank($tenant->identifier)) {
                $tenant->identifier = $tenant->generateIdentifier();
            }
        });
    }

    /**
     * Slug único derivado do nome, usado para identificar a empresa no app.
     */
    public function generateIdentifier(): string
    {
        $base = Str::limit(Str::slug($this->name) ?: 'empresa', 50, '');
        $identifier = $base;
        $suffix = 2;

        while (static::query()->where('identifier', $identifier)->exists()) {
            $identifier = "{$base}-{$suffix}";
            $suffix++;
        }

        return $identifier;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->withoutTenancy();
    }

    public function isUmbrella(): bool
    {
        return $this->parent_id === null;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset("storage/{$this->logo_path}") : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? asset("storage/{$this->favicon_path}") : null;
    }

    public function signatureUrl(): ?string
    {
        return $this->signature_path ? asset("storage/{$this->signature_path}") : null;
    }

    public function appIconUrl(): ?string
    {
        return $this->app_icon_path ? asset("storage/{$this->app_icon_path}") : null;
    }

    public function appLogoUrl(): ?string
    {
        return $this->app_logo_path ? asset("storage/{$this->app_logo_path}") : null;
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
