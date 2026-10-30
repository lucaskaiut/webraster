<?php

namespace App\Modules\Tenant\Models;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Enums\TenantModule;
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
        'is_active',
        'modules',
        'document',
        'email',
        'phone',
        'logo_path',
        'favicon_path',
        'signature_path',
        'late_fine_percent',
        'late_interest_percent',
        'vehicle_alert_defaults',
        'app_name',
        'app_icon_path',
        'app_logo_path',
        'app_primary_color',
        'app_secondary_color',
        'require_equipment_loan_acceptance',
        'equipment_loan_term',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'modules' => 'array',
            'vehicle_alert_defaults' => 'array',
            'late_fine_percent' => 'decimal:2',
            'late_interest_percent' => 'decimal:2',
            'require_equipment_loan_acceptance' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (blank($tenant->identifier)) {
                $tenant->identifier = $tenant->generateIdentifier();
            }

            if ($tenant->modules === null) {
                $tenant->modules = TenantModule::values();
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

    /**
     * Tenant inativo tem o acesso bloqueado. O umbrella nunca é inativado.
     */
    public function isActive(): bool
    {
        return $this->isUmbrella() || $this->is_active !== false;
    }

    /**
     * O umbrella tem todos os módulos; filhos, os liberados pelo umbrella.
     */
    public function hasModule(TenantModule $module): bool
    {
        return $this->isUmbrella() || in_array($module->value, $this->modules ?? [], true);
    }

    /**
     * @return list<TenantModule>
     */
    public function enabledModules(): array
    {
        return array_values(array_filter(
            TenantModule::cases(),
            fn (TenantModule $module): bool => $this->hasModule($module),
        ));
    }

    /**
     * Permissões do núcleo sempre valem; as de módulo exigem ao menos um
     * dos módulos que as liberam.
     */
    public function allowsPermission(Permission|string $permission): bool
    {
        $modules = TenantModule::providing($permission);

        if ($modules === []) {
            return true;
        }

        foreach ($modules as $module) {
            if ($this->hasModule($module)) {
                return true;
            }
        }

        return false;
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
