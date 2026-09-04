<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\RecurrenceUnit;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class PlanService
{
    /**
     * @param  array{name: string, description?: ?string, price: float|string|int, recurrence_value: int, recurrence_unit: string, free_trial_days?: int, active?: bool}  $data
     */
    public function create(array $data): Plan
    {
        $this->assertCanManagePlans();

        return Plan::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'recurrence_value' => $data['recurrence_value'],
            'recurrence_unit' => RecurrenceUnit::from($data['recurrence_unit']),
            'free_trial_days' => $data['free_trial_days'] ?? 0,
            'active' => $data['active'] ?? true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data): Plan
    {
        $this->assertCanManagePlans();

        $attributes = Arr::only($data, [
            'name',
            'description',
            'price',
            'recurrence_value',
            'recurrence_unit',
            'free_trial_days',
            'active',
        ]);

        if (isset($attributes['recurrence_unit'])) {
            $attributes['recurrence_unit'] = RecurrenceUnit::from($attributes['recurrence_unit']);
        }

        $plan->fill($attributes);
        $plan->save();

        return $plan->refresh();
    }

    public function deactivate(Plan $plan): Plan
    {
        return $this->update($plan, ['active' => false]);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Plan::query()
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Planos ativos disponíveis para assinatura dos filhos do umbrella informado.
     *
     * @return Collection<int, Plan>
     */
    public function catalogForTenant(Tenant $subscriber): Collection
    {
        $ownerId = $subscriber->parent_id ?? $subscriber->getKey();

        return Plan::query()
            ->withoutTenancy()
            ->where('tenant_id', $ownerId)
            ->where('active', true)
            ->orderBy('price')
            ->get();
    }

    /**
     * Catálogo público do tenant raiz (para registro de novas empresas).
     *
     * @return Collection<int, Plan>
     */
    public function publicCatalog(): Collection
    {
        $rootId = Tenant::query()->orderBy('id')->value('id');

        if ($rootId === null) {
            return new Collection;
        }

        return Plan::query()
            ->withoutTenancy()
            ->where('tenant_id', $rootId)
            ->where('active', true)
            ->orderBy('price')
            ->get();
    }

    public function findActiveForSubscription(string $planUuid, Tenant $subscriber): Plan
    {
        $ownerId = $subscriber->parent_id ?? $subscriber->getKey();

        $plan = Plan::query()
            ->withoutTenancy()
            ->where('uuid', $planUuid)
            ->where('tenant_id', $ownerId)
            ->where('active', true)
            ->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_id' => ['Plano inválido ou indisponível para esta empresa.'],
            ]);
        }

        return $plan;
    }

    private function assertCanManagePlans(): void
    {
        if (! TenantContext::isResolved()) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant não resolvido.'],
            ]);
        }

        $tenant = TenantContext::tenant();

        if (! $tenant->isUmbrella()) {
            throw ValidationException::withMessages([
                'plan' => ['Apenas tenants umbrella (raiz) podem gerenciar planos.'],
            ]);
        }
    }
}
