<?php

namespace App\Modules\Poi\Services;

use App\Modules\Poi\Models\Poi;
use App\Modules\Poi\Models\PoiCategory;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PoiService
{
    /**
     * @return list<array{name: string, slug: string, color: string, sort_order: int}>
     */
    public static function defaultCategories(): array
    {
        return [
            ['name' => 'Cliente', 'slug' => 'cliente', 'color' => '#0f766e', 'sort_order' => 10],
            ['name' => 'Oficina', 'slug' => 'oficina', 'color' => '#b45309', 'sort_order' => 20],
            ['name' => 'Posto', 'slug' => 'posto', 'color' => '#1d4ed8', 'sort_order' => 30],
            ['name' => 'Filial', 'slug' => 'filial', 'color' => '#7c3aed', 'sort_order' => 40],
            ['name' => 'Base', 'slug' => 'base', 'color' => '#be123c', 'sort_order' => 50],
            ['name' => 'Garagem', 'slug' => 'garagem', 'color' => '#334155', 'sort_order' => 60],
            ['name' => 'Outros', 'slug' => 'outros', 'color' => '#64748b', 'sort_order' => 70],
        ];
    }

    public function ensureDefaultCategories(?Tenant $tenant = null): void
    {
        $tenantId = $tenant?->getKey() ?? TenantContext::tenantId();

        if ($tenantId === null) {
            return;
        }

        foreach (self::defaultCategories() as $category) {
            $exists = PoiCategory::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('slug', $category['slug'])
                ->exists();

            if ($exists) {
                continue;
            }

            $model = new PoiCategory;
            $model->forceFill([
                'tenant_id' => $tenantId,
                'slug' => $category['slug'],
                'name' => $category['name'],
                'color' => $category['color'],
                'sort_order' => $category['sort_order'],
                'is_active' => true,
            ])->save();
        }
    }

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $clientId = null,
        ?int $categoryId = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator {
        $this->ensureDefaultCategories();

        return Poi::query()
            ->with(['client', 'category'])
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->when($categoryId !== null, fn ($query) => $query->where('poi_category_id', $categoryId))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return Collection<int, Poi>
     */
    public function listForMap(?int $clientId = null, ?int $categoryId = null, bool $activeOnly = true): Collection
    {
        return Poi::query()
            ->with(['client', 'category'])
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->when($categoryId !== null, fn ($query) => $query->where('poi_category_id', $categoryId))
            ->orderBy('name')
            ->limit(500)
            ->get();
    }

    /**
     * @return Collection<int, PoiCategory>
     */
    public function listCategories(bool $activeOnly = true): Collection
    {
        $this->ensureDefaultCategories();

        return PoiCategory::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Poi
    {
        $this->ensureDefaultCategories();

        return Poi::query()->create($this->payload($data))->load(['client', 'category']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Poi $poi, array $data): Poi
    {
        $poi->fill($this->payload($data));
        $poi->save();

        return $poi->refresh()->load(['client', 'category']);
    }

    public function delete(Poi $poi): void
    {
        $poi->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return Arr::only($data, [
            'client_id',
            'poi_category_id',
            'name',
            'description',
            'latitude',
            'longitude',
            'address',
            'is_active',
        ]);
    }

    public function resolveCategoryId(?string $uuid): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return PoiCategory::query()->where('uuid', $uuid)->value('id');
    }
}
