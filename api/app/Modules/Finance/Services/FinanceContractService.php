<?php

namespace App\Modules\Finance\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Enums\BillingPeriodicity;
use App\Modules\Finance\Enums\ContractStatus;
use App\Modules\Finance\Enums\SubscriptionStatus;
use App\Modules\Finance\Events\SubscriptionCreated;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceContractService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['client', 'plan', 'subscription'])
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): FinanceContract
    {
        $payload = $this->payload($data);
        $this->assertRelations($payload);

        $contract = DB::transaction(function () use ($payload, $actor) {
            $payload['number'] = $this->nextNumber((int) TenantContext::tenantId());
            $payload['created_by'] = $actor->getKey();

            if (! isset($payload['status'])) {
                $payload['status'] = ContractStatus::ACTIVE;
            }

            $contract = FinanceContract::query()->create($payload);

            if ($contract->status === ContractStatus::ACTIVE || $contract->auto_renew) {
                $this->createSubscriptionFor($contract);
            }

            return $contract;
        });

        return $contract->load(['client', 'plan', 'subscription', 'creator']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinanceContract $contract, array $data): FinanceContract
    {
        if ($contract->status === ContractStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Contratos cancelados não podem ser editados.'],
            ]);
        }

        $payload = $this->payload($data, $contract);
        $this->assertRelations($payload, $contract);

        $contract->fill($payload);
        $contract->save();

        return $contract->refresh()->load(['client', 'plan', 'subscription', 'creator']);
    }

    public function changeStatus(FinanceContract $contract, ContractStatus $next): FinanceContract
    {
        if ($contract->status === ContractStatus::CANCELLED && $next !== ContractStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Contratos cancelados não podem ser reativados por esta ação.'],
            ]);
        }

        $contract->status = $next;

        if ($next === ContractStatus::CANCELLED) {
            $contract->ends_at = $contract->ends_at ?? now()->toDateString();
        }

        $contract->save();

        $subscription = $contract->subscription;
        if ($subscription) {
            if ($next === ContractStatus::CANCELLED) {
                $subscription->status = SubscriptionStatus::CANCELLED;
                $subscription->cancelled_at = now();
                $subscription->save();
            } elseif ($next === ContractStatus::SUSPENDED) {
                $subscription->status = SubscriptionStatus::SUSPENDED;
                $subscription->save();
            } elseif ($next === ContractStatus::ACTIVE) {
                $subscription->status = SubscriptionStatus::ACTIVE;
                $subscription->cancelled_at = null;
                $subscription->save();
            }
        } elseif ($next === ContractStatus::ACTIVE) {
            $this->createSubscriptionFor($contract);
        }

        return $contract->refresh()->load(['client', 'plan', 'subscription', 'creator']);
    }

    public function delete(FinanceContract $contract): void
    {
        if ($contract->status === ContractStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'status' => ['Cancele o contrato antes de excluí-lo.'],
            ]);
        }

        $contract->delete();
    }

    public function nextNumber(int $tenantId): int
    {
        $max = FinanceContract::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('number');

        return ((int) $max) + 1;
    }

    private function createSubscriptionFor(FinanceContract $contract): FinanceSubscription
    {
        $startsAt = CarbonImmutable::parse($contract->starts_at);
        $dueDay = min(max((int) $contract->due_day, 1), 28);
        $nextBilling = $startsAt->day($dueDay);
        if ($nextBilling->lessThan($startsAt)) {
            $nextBilling = $nextBilling->addMonth();
        }

        $subscription = FinanceSubscription::query()->create([
            'contract_id' => $contract->getKey(),
            'client_id' => $contract->client_id,
            'status' => SubscriptionStatus::ACTIVE,
            'periodicity' => $contract->periodicity,
            'next_billing_at' => $nextBilling->toDateString(),
        ]);

        event(new SubscriptionCreated($subscription));

        return $subscription;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters)
    {
        $query = FinanceContract::query();

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('notes', 'like', "%{$search}%");

                if (preg_match('/^CTR-?0*(\d+)$/i', $search, $matches)) {
                    $builder->orWhere('number', (int) $matches[1]);
                } elseif (ctype_digit($search)) {
                    $builder->orWhere('number', (int) $search);
                }

                $builder->orWhereHas('client', function ($clientQuery) use ($search): void {
                    $clientQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?FinanceContract $existing = null): array
    {
        $payload = Arr::only($data, [
            'client_id',
            'plan_id',
            'status',
            'starts_at',
            'ends_at',
            'periodicity',
            'due_day',
            'amount_cents',
            'discount_cents',
            'fine_percent',
            'interest_percent',
            'device_quantity',
            'auto_renew',
            'block_on_overdue',
            'block_after_days',
            'notes',
        ]);

        if (isset($payload['status'])) {
            $payload['status'] = $payload['status'] instanceof ContractStatus
                ? $payload['status']
                : ContractStatus::from((string) $payload['status']);
        }

        if (isset($payload['periodicity'])) {
            $payload['periodicity'] = $payload['periodicity'] instanceof BillingPeriodicity
                ? $payload['periodicity']
                : BillingPeriodicity::from((string) $payload['periodicity']);
        } elseif ($existing === null) {
            $payload['periodicity'] = BillingPeriodicity::MONTHLY;
        }

        if ($existing === null && empty($payload['client_id'])) {
            throw ValidationException::withMessages(['client_id' => ['O cliente é obrigatório.']]);
        }

        if ($existing === null && empty($payload['starts_at'])) {
            throw ValidationException::withMessages(['starts_at' => ['A data de início é obrigatória.']]);
        }

        if (isset($payload['starts_at'])) {
            $payload['starts_at'] = CarbonImmutable::parse($payload['starts_at'])->toDateString();
        }

        if (array_key_exists('ends_at', $payload) && $payload['ends_at']) {
            $payload['ends_at'] = CarbonImmutable::parse($payload['ends_at'])->toDateString();
        }

        if ($existing === null && ! isset($payload['amount_cents']) && ! empty($payload['plan_id'])) {
            $plan = FinancePlan::query()->find($payload['plan_id']);
            if ($plan) {
                $payload['amount_cents'] = $plan->amount_cents;
                $payload['periodicity'] = $payload['periodicity'] ?? $plan->periodicity;
            }
        }

        if ($existing === null && ! isset($payload['amount_cents'])) {
            throw ValidationException::withMessages(['amount_cents' => ['O valor é obrigatório.']]);
        }

        foreach (['auto_renew', 'block_on_overdue'] as $boolField) {
            if (array_key_exists($boolField, $payload)) {
                $payload[$boolField] = (bool) $payload[$boolField];
            }
        }

        if ($existing === null) {
            $payload['discount_cents'] = (int) ($payload['discount_cents'] ?? 0);
            $payload['due_day'] = (int) ($payload['due_day'] ?? 10);
            $payload['device_quantity'] = (int) ($payload['device_quantity'] ?? 0);
            $payload['auto_renew'] = (bool) ($payload['auto_renew'] ?? true);
            $payload['block_on_overdue'] = (bool) ($payload['block_on_overdue'] ?? true);
            $payload['block_after_days'] = (int) ($payload['block_after_days'] ?? 5);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertRelations(array $payload, ?FinanceContract $existing = null): void
    {
        $clientId = $payload['client_id'] ?? $existing?->client_id;
        $planId = array_key_exists('plan_id', $payload) ? $payload['plan_id'] : $existing?->plan_id;

        if ($clientId) {
            $client = Client::query()->find($clientId);
            if ($client === null) {
                throw ValidationException::withMessages(['client_id' => ['Cliente inválido.']]);
            }
        }

        if ($planId) {
            $plan = FinancePlan::query()->find($planId);
            if ($plan === null) {
                throw ValidationException::withMessages(['plan_id' => ['Plano inválido.']]);
            }

            $deviceQuantity = (int) ($payload['device_quantity'] ?? $existing?->device_quantity ?? 0);
            if ($plan->device_limit !== null && $deviceQuantity > $plan->device_limit) {
                throw ValidationException::withMessages([
                    'device_quantity' => [sprintf(
                        'A quantidade de dispositivos excede o limite do plano (%d).',
                        $plan->device_limit,
                    )],
                ]);
            }
        }
    }
}
