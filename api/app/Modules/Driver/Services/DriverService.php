<?php

namespace App\Modules\Driver\Services;

use App\Modules\Driver\Models\Driver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class DriverService
{
    public function paginate(int $perPage = 15, ?string $search = null, ?int $clientId = null): LengthAwarePaginator
    {
        return Driver::query()
            ->with('client')
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%")
                        ->orWhere('cnh_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Driver
    {
        return Driver::query()->create($this->payload($data))->load('client');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Driver $driver, array $data): Driver
    {
        $driver->fill($this->payload($data));
        $driver->save();

        return $driver->refresh()->load('client');
    }

    public function delete(Driver $driver): void
    {
        $driver->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return Arr::only($data, [
            'client_id',
            'name',
            'document',
            'phone',
            'email',
            'cnh_number',
            'cnh_expires_at',
            'notes',
            'is_active',
        ]);
    }
}
