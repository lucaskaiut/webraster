<?php

namespace App\Modules\Contract\Services;

use App\Modules\Contract\Models\Contract;
use App\Modules\Shared\Support\QuerySort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ContractService
{
    public function paginate(int $perPage = 15, ?string $search = null, ?string $sort = null): LengthAwarePaginator
    {
        $query = Contract::query()
            ->when(filled($search), function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            });

        return QuerySort::apply($query, $sort, ['name', 'created_at'], 'name:asc')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contract
    {
        return Contract::query()->create($this->payload($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contract $contract, array $data): Contract
    {
        $contract->fill($this->payload($data));
        $contract->save();

        return $contract->refresh();
    }

    public function delete(Contract $contract): void
    {
        $contract->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return Arr::only($data, [
            'name',
            'body',
        ]);
    }
}
