<?php

namespace App\Modules\Service\Services;

use App\Modules\Service\Models\Service;
use App\Modules\Shared\Support\QuerySort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ServiceService
{
    public function paginate(int $perPage = 15, ?string $search = null, ?string $sort = null): LengthAwarePaginator
    {
        $query = Service::query()
            ->with('linkedServices')
            ->when(filled($search), function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            });

        return QuerySort::apply($query, $sort, ['name', 'amount_cents', 'created_at'], 'name:asc')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Service
    {
        return DB::transaction(function () use ($data): Service {
            $service = Service::query()->create($this->payload($data));

            if (array_key_exists('linked_service_ids', $data)) {
                $service->linkedServices()->sync($data['linked_service_ids'] ?? []);
            }

            return $service->refresh()->load('linkedServices');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Service $service, array $data): Service
    {
        return DB::transaction(function () use ($service, $data): Service {
            $service->fill($this->payload($data));
            $service->save();

            if (array_key_exists('linked_service_ids', $data)) {
                $service->linkedServices()->sync($data['linked_service_ids'] ?? []);
            }

            return $service->refresh()->load('linkedServices');
        });
    }

    public function delete(Service $service): void
    {
        $service->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $payload = Arr::only($data, [
            'name',
            'amount_cents',
            'estimated_minutes',
            'commission_type',
            'commission_value',
        ]);

        if (array_key_exists('commission_type', $payload) && blank($payload['commission_type'])) {
            $payload['commission_type'] = null;
            $payload['commission_value'] = 0;
        }

        return $payload;
    }
}
