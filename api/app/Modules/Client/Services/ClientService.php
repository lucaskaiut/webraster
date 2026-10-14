<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientOrder;
use App\Modules\Driver\Models\Driver;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Services\FinanceSubscriptionService;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Poi\Models\Poi;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Shared\Support\QuerySort;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ClientService
{
    public function __construct(
        private readonly FinanceSubscriptionService $subscriptions,
        private readonly ClientContractService $contracts,
        private readonly ClientUserService $clientUsers,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?bool $delinquent = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $query = Client::query()
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
            ->when($delinquent === true, function ($query): void {
                $query->whereHas(
                    'billings',
                    fn ($query) => $query->where('status', BillingStatus::OVERDUE->value),
                );
            })
            ->when($delinquent === false, function ($query): void {
                $query->whereDoesntHave(
                    'billings',
                    fn ($query) => $query->where('status', BillingStatus::OVERDUE->value),
                );
            });

        return QuerySort::apply(
            $query,
            $sort,
            ['name', 'document', 'email', 'phone', 'city', 'state', 'is_active', 'created_at'],
            'name:asc',
        )->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            $client = Client::query()->create($this->payload($data));

            if ($client->without_contract) {
                $this->contracts->markWithoutContract($client);
            }

            if (! empty($client->plan_id)) {
                $plan = FinancePlan::query()->find($client->plan_id);
                if ($plan) {
                    $this->subscriptions->syncForClient($client, $plan);
                }
            }

            // Disponibiliza o usuário principal com os dados já cadastrados.
            $this->clientUsers->ensurePrimaryUser($client);

            return $client->refresh()->load('plan');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {
            $previousPlanId = $client->plan_id;
            $payload = $this->payload($data);

            $withoutContractChanged = array_key_exists('without_contract', $payload)
                && (bool) $client->without_contract !== (bool) $payload['without_contract'];

            $client->fill($payload);
            $client->save();

            if ($withoutContractChanged) {
                if ($client->without_contract) {
                    $this->contracts->markWithoutContract($client);
                } else {
                    $this->contracts->clearWithoutContract($client);
                }
            }

            if (array_key_exists('plan_id', $payload) && (int) $previousPlanId !== (int) $client->plan_id) {
                if ($client->plan_id) {
                    $this->subscriptions->syncForClient($client->fresh());
                }
            }

            return $client->refresh()->load('plan');
        });
    }

    public function delete(Client $client): void
    {
        DB::transaction(function () use ($client): void {
            User::query()
                ->where('client_id', $client->getKey())
                ->get()
                ->each(function (User $user): void {
                    $user->tokens()->delete();
                    $user->delete();
                });

            Driver::query()->where('client_id', $client->getKey())->delete();
            Vehicle::query()->where('client_id', $client->getKey())->delete();
            Geofence::query()->where('client_id', $client->getKey())->delete();
            Poi::query()->where('client_id', $client->getKey())->delete();
            ServiceOrder::query()->where('client_id', $client->getKey())->delete();
            ClientOrder::query()->where('client_id', $client->getKey())->delete();

            $client->delete();
        });
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
            'without_contract',
            'plan_id',
        ]);
    }
}
