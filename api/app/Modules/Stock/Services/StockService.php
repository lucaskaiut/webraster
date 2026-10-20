<?php

namespace App\Modules\Stock\Services;

use App\Modules\Shared\Support\QuerySort;
use App\Modules\Stock\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function paginate(
        int|string $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $query = Stock::query()
            ->withCount(['equipments', 'technicians'])
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        QuerySort::apply(
            $query,
            $sort,
            ['name', 'is_active', 'created_at'],
            'name:asc',
        );

        if ($perPage === 'all') {
            $total = (clone $query)->toBase()->getCountForPagination();

            return $query->paginate(max(1, $total));
        }

        return $query->paginate(min(max((int) $perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Stock
    {
        $stock = Stock::query()->create($this->payload($data));

        return $stock->loadCount(['equipments', 'technicians']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Stock $stock, array $data): Stock
    {
        $stock->fill($this->payload($data));
        $stock->save();

        return $stock->refresh()->loadCount(['equipments', 'technicians']);
    }

    public function delete(Stock $stock): void
    {
        if ($stock->equipments()->exists()) {
            throw ValidationException::withMessages([
                'stock' => ['Este estoque possui equipamentos vinculados. Transfira-os antes de excluir.'],
            ]);
        }

        if ($stock->technicians()->exists()) {
            throw ValidationException::withMessages([
                'stock' => ['Este estoque está vinculado a técnicos. Altere o estoque dos técnicos antes de excluir.'],
            ]);
        }

        $stock->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $payload = Arr::only($data, ['name', 'description', 'is_active']);

        if (array_key_exists('name', $payload)) {
            $payload['name'] = trim((string) $payload['name']);
        }

        if (array_key_exists('description', $payload)) {
            $payload['description'] = filled($payload['description'])
                ? trim((string) $payload['description'])
                : null;
        }

        return $payload;
    }
}
