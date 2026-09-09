<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ClientService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Client::query()
            ->when(filled($search), function ($query) use ($search): void {
                $digits = preg_replace('/\D+/', '', $search) ?: null;

                $query->where(function ($query) use ($search, $digits): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");

                    if (filled($digits)) {
                        $query->orWhere('document', 'like', "%{$digits}%");
                    }
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Client
    {
        return Client::query()->create($this->payload($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, array $data): Client
    {
        $client->fill($this->payload($data));
        $client->save();

        return $client->refresh();
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return Arr::only($data, [
            'name',
            'legal_name',
            'trade_name',
            'document',
            'state_registration',
            'email',
            'financial_email',
            'phone',
            'street',
            'number',
            'complement',
            'neighborhood',
            'city',
            'state',
            'zip',
            'is_active',
        ]);
    }
}
