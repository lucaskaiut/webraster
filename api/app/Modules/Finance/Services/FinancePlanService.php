<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\BillingPeriodicity;
use App\Modules\Finance\Models\FinancePlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FinancePlanService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = FinancePlan::query();

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function find(FinancePlan $plan): FinancePlan
    {
        return $plan;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FinancePlan
    {
        return FinancePlan::query()->create($this->payload($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinancePlan $plan, array $data): FinancePlan
    {
        $plan->fill($this->payload($data, $plan));
        $plan->save();

        return $plan->refresh();
    }

    public function delete(FinancePlan $plan): void
    {
        $plan->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?FinancePlan $existing = null): array
    {
        $payload = Arr::only($data, [
            'name',
            'description',
            'amount_cents',
            'periodicity',
            'device_limit',
            'is_active',
        ]);

        if (isset($payload['periodicity'])) {
            $payload['periodicity'] = $payload['periodicity'] instanceof BillingPeriodicity
                ? $payload['periodicity']
                : BillingPeriodicity::from((string) $payload['periodicity']);
        } elseif ($existing === null) {
            $payload['periodicity'] = BillingPeriodicity::MONTHLY;
        }

        if ($existing === null && empty($payload['name'])) {
            throw ValidationException::withMessages(['name' => ['O nome é obrigatório.']]);
        }

        if ($existing === null && ! isset($payload['amount_cents'])) {
            throw ValidationException::withMessages(['amount_cents' => ['O valor é obrigatório.']]);
        }

        if (array_key_exists('is_active', $payload)) {
            $payload['is_active'] = (bool) $payload['is_active'];
        } elseif ($existing === null) {
            $payload['is_active'] = true;
        }

        return $payload;
    }
}
