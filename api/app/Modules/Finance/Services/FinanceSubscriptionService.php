<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\ContractStatus;
use App\Modules\Finance\Enums\SubscriptionStatus;
use App\Modules\Finance\Events\SubscriptionCanceled;
use App\Modules\Finance\Events\SubscriptionCreated;
use App\Modules\Finance\Models\FinanceSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class FinanceSubscriptionService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = FinanceSubscription::query()->with(['client', 'contract.plan']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->whereHas('client', function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function cancel(FinanceSubscription $subscription): FinanceSubscription
    {
        if ($subscription->status === SubscriptionStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['A assinatura já está cancelada.'],
            ]);
        }

        $subscription->status = SubscriptionStatus::CANCELLED;
        $subscription->cancelled_at = now();
        $subscription->save();

        $contract = $subscription->contract;
        if ($contract && $contract->status !== ContractStatus::CANCELLED) {
            $contract->status = ContractStatus::CANCELLED;
            $contract->ends_at = $contract->ends_at ?? now()->toDateString();
            $contract->save();
        }

        event(new SubscriptionCanceled($subscription));

        return $subscription->refresh()->load(['client', 'contract.plan']);
    }

    public function reactivate(FinanceSubscription $subscription): FinanceSubscription
    {
        if ($subscription->status === SubscriptionStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'status' => ['A assinatura já está ativa.'],
            ]);
        }

        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->cancelled_at = null;
        if ($subscription->next_billing_at === null || $subscription->next_billing_at->isPast()) {
            $subscription->next_billing_at = now()->toDateString();
        }
        $subscription->save();

        $contract = $subscription->contract;
        if ($contract) {
            $contract->status = ContractStatus::ACTIVE;
            $contract->ends_at = null;
            $contract->save();
        }

        event(new SubscriptionCreated($subscription));

        return $subscription->refresh()->load(['client', 'contract.plan']);
    }
}
