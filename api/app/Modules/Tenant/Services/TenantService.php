<?php

namespace App\Modules\Tenant\Services;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Tenant\Enums\TenantModule;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantService
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly UserService $users,
    ) {}

    /**
     * @param  array{name: string, document: string, email: string, phone: ?string, parent_id?: int|null}  $data
     */
    public function create(array $data): Tenant
    {
        if (! array_key_exists('parent_id', $data)) {
            $data['parent_id'] = $this->resolveParentId();
        }

        return Tenant::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        unset($data['parent_id']);

        $tenant->fill($data);
        $tenant->save();

        return $tenant->refresh();
    }

    /**
     * Lista os tenants filhos do umbrella informado. A contagem de usuários
     * ignora o escopo do tenant em contexto (o umbrella).
     */
    public function paginateChildren(Tenant $umbrella, int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Tenant::query()
            ->where('parent_id', $umbrella->getKey())
            ->withCount(['users' => fn ($query) => $query->withoutTenancy()->withoutClientScope()])
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Visão geral das empresas filhas para o dashboard do umbrella.
     *
     * @return array{companies: array{total: int, active: int, inactive: int}, users: int, recent: Collection<int, Tenant>}
     */
    public function overview(Tenant $umbrella): array
    {
        $children = Tenant::query()
            ->where('parent_id', $umbrella->getKey())
            ->withCount(['users' => fn ($query) => $query->withoutTenancy()->withoutClientScope()])
            ->orderByDesc('created_at')
            ->get();

        $active = $children->filter(fn (Tenant $child): bool => $child->isActive());

        return [
            'companies' => [
                'total' => $children->count(),
                'active' => $active->count(),
                'inactive' => $children->count() - $active->count(),
            ],
            'users' => (int) $children->sum('users_count'),
            'recent' => $children->take(5)->values(),
        ];
    }

    public function findChild(Tenant $umbrella, Tenant $child): Tenant
    {
        if ((int) $child->parent_id !== (int) $umbrella->getKey()) {
            throw ValidationException::withMessages([
                'child' => ['Empresa não encontrada neste grupo.'],
            ]);
        }

        return $child;
    }

    /**
     * Provisiona um tenant filho sob o umbrella: tenant + perfis padrão +
     * usuário administrador (não master). O acesso é controlado
     * manualmente pela situação e pelos módulos; nenhuma cobrança é gerada.
     *
     * @param  array{name: string, document: string, email: string, phone: ?string, is_active?: bool, modules?: list<string>}  $tenantData
     * @param  array{name: string, email: string, phone?: ?string, document?: ?string, password: string}  $userData
     * @return array{tenant: Tenant, user: User}
     */
    public function createChild(Tenant $umbrella, array $tenantData, array $userData): array
    {
        return DB::transaction(function () use ($umbrella, $tenantData, $userData): array {
            $tenant = $this->create([
                ...$this->normalizeAccess($tenantData),
                'parent_id' => $umbrella->getKey(),
            ]);

            $roles = $this->roles->createDefaultRolesFor($tenant);

            $user = $this->users->createForTenant($tenant, $userData);
            $user->assignRole($roles[DefaultRole::ADMINISTRATOR->value]);

            return [
                'tenant' => $tenant->refresh(),
                'user' => $user->load('roles.permissions'),
            ];
        });
    }

    /**
     * Atualiza dados, situação e módulos da empresa filha.
     *
     * @param  array{name: string, document: string, email: string, phone: ?string, is_active?: bool, modules?: list<string>}  $tenantData
     */
    public function updateChild(
        Tenant $umbrella,
        Tenant $child,
        array $tenantData,
    ): Tenant {
        $this->findChild($umbrella, $child);

        return $this->update($child, $this->normalizeAccess($tenantData));
    }

    /**
     * Módulos escolhidos levam junto os que eles exigem.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeAccess(array $data): array
    {
        if (array_key_exists('modules', $data)) {
            $data['modules'] = TenantModule::withRequirements($data['modules'] ?? []);
        }

        return $data;
    }

    /**
     * Novos tenants ficam sob o primeiro cadastrado na base.
     * O primeiro tenant permanece como raiz (parent_id nulo).
     */
    private function resolveParentId(): ?int
    {
        $firstTenantId = Tenant::query()->orderBy('id')->value('id');

        return $firstTenantId !== null ? (int) $firstTenantId : null;
    }
}
