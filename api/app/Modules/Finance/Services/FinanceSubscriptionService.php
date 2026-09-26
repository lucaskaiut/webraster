<?php

namespace App\Modules\Finance\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientOrder;
use App\Modules\Finance\Events\SubscriptionCanceled;
use App\Modules\Finance\Events\SubscriptionCreated;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Shared\Subscription\Enums\SubscriptionStatus;
use App\Modules\Shared\Subscription\Support\NextBillingDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceSubscriptionService
{
    public function __construct(
        private readonly DelinquencyService $delinquency,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = FinanceSubscription::query()->with(['client', 'plan']);

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

    public function syncForClient(Client $client, ?FinancePlan $plan = null): ?FinanceSubscription
    {
        $plan ??= $client->plan_id
            ? FinancePlan::query()->find($client->plan_id)
            : null;

        if ($plan === null) {
            return $this->currentForClient($client);
        }

        return $this->assignPlan($client, $plan);
    }

    /**
     * Cria ou atualiza a assinatura a partir do pedido (serviços × veículos),
     * sem exigir um FinancePlan.
     *
     * @param  array<string, mixed>  $options
     */
    public function assignFromOrder(Client $client, ClientOrder $order, array $options = []): FinanceSubscription
    {
        $order->loadMissing('items');

        $planName = $order->items
            ->pluck('service_name')
            ->filter()
            ->unique()
            ->implode(' + ');

        if ($planName === '') {
            $planName = 'Pedido';
        }

        $periodicity = $order->periodicity ?? BillingPeriodicity::MONTHLY;
        $dueDay = min(max((int) ($options['due_day'] ?? $order->due_day ?? 10), 1), 28);

        return DB::transaction(function () use ($client, $order, $planName, $periodicity, $dueDay, $options) {
            $subscription = $this->currentForClient($client);

            if ($subscription === null) {
                $this->assertNoOtherSubscription($client);

                $nextBilling = $this->resolveInitialNextBilling($dueDay, $options['next_billing_at'] ?? null);

                $subscription = FinanceSubscription::query()->create([
                    'client_id' => $client->getKey(),
                    'plan_id' => null,
                    'plan_name' => $planName,
                    'plan_price_cents' => (int) $order->total_cents,
                    'plan_periodicity' => $periodicity,
                    'status' => SubscriptionStatus::ACTIVE,
                    'due_day' => $dueDay,
                    'block_on_overdue' => (bool) ($options['block_on_overdue'] ?? true),
                    'block_after_days' => (int) ($options['block_after_days'] ?? 5),
                    'next_billing_at' => $nextBilling->toDateString(),
                    'started_at' => now(),
                ]);

                event(new SubscriptionCreated($subscription));
            } else {
                $subscription->plan_id = null;
                $subscription->plan_name = $planName;
                $subscription->plan_price_cents = (int) $order->total_cents;
                $subscription->plan_periodicity = $periodicity;
                $subscription->due_day = $dueDay;

                if (array_key_exists('next_billing_at', $options) && $options['next_billing_at']) {
                    $subscription->next_billing_at = CarbonImmutable::parse((string) $options['next_billing_at'])->toDateString();
                }

                if ($subscription->status === SubscriptionStatus::CANCELLED) {
                    $subscription->status = SubscriptionStatus::ACTIVE;
                    $subscription->cancelled_at = null;
                    $subscription->started_at = $subscription->started_at ?? now();
                    if ($subscription->next_billing_at === null) {
                        $subscription->next_billing_at = $this->resolveInitialNextBilling($dueDay)->toDateString();
                    }
                }

                $subscription->save();
            }

            if ($client->plan_id !== null) {
                $client->forceFill(['plan_id' => null])->save();
            }

            return $subscription->load(['client', 'plan']);
        });
    }

    public function assignPlan(Client $client, FinancePlan $plan, array $options = []): FinanceSubscription
    {
        if ((int) $plan->tenant_id !== (int) $client->tenant_id) {
            throw ValidationException::withMessages([
                'plan_id' => ['Plano inválido para este tenant.'],
            ]);
        }

        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => ['O plano selecionado está inativo.'],
            ]);
        }

        return DB::transaction(function () use ($client, $plan, $options) {
            $subscription = $this->currentForClient($client);

            if ($subscription === null) {
                $this->assertNoOtherSubscription($client);

                $dueDay = min(max((int) ($options['due_day'] ?? 10), 1), 28);
                $nextBilling = $this->resolveInitialNextBilling($dueDay, $options['next_billing_at'] ?? null);

                $subscription = FinanceSubscription::query()->create([
                    'client_id' => $client->getKey(),
                    'plan_id' => $plan->getKey(),
                    'plan_name' => $plan->name,
                    'plan_price_cents' => $plan->amount_cents,
                    'plan_periodicity' => $plan->periodicity,
                    'status' => SubscriptionStatus::ACTIVE,
                    'due_day' => $dueDay,
                    'block_on_overdue' => (bool) ($options['block_on_overdue'] ?? true),
                    'block_after_days' => (int) ($options['block_after_days'] ?? 5),
                    'next_billing_at' => $nextBilling->toDateString(),
                    'started_at' => now(),
                ]);

                if ((int) $client->plan_id !== (int) $plan->getKey()) {
                    $client->forceFill(['plan_id' => $plan->getKey()])->save();
                }

                event(new SubscriptionCreated($subscription));

                return $subscription->load(['client', 'plan']);
            }

            return $this->changePlan($subscription, $plan, $options);
        });
    }

    public function changePlan(
        FinanceSubscription $subscription,
        FinancePlan $plan,
        array $options = [],
    ): FinanceSubscription {
        if ((int) $plan->tenant_id !== (int) $subscription->tenant_id) {
            throw ValidationException::withMessages([
                'plan_id' => ['Plano inválido para este tenant.'],
            ]);
        }

        $subscription->plan_id = $plan->getKey();
        $subscription->plan_name = $plan->name;
        $subscription->plan_price_cents = $plan->amount_cents;
        $subscription->plan_periodicity = $plan->periodicity;

        if (array_key_exists('due_day', $options)) {
            $subscription->due_day = min(max((int) $options['due_day'], 1), 28);
        }

        if (array_key_exists('block_on_overdue', $options)) {
            $subscription->block_on_overdue = (bool) $options['block_on_overdue'];
        }

        if (array_key_exists('block_after_days', $options)) {
            $subscription->block_after_days = (int) $options['block_after_days'];
        }

        if (array_key_exists('next_billing_at', $options) && $options['next_billing_at']) {
            $subscription->next_billing_at = CarbonImmutable::parse((string) $options['next_billing_at'])->toDateString();
        }

        if ($subscription->status === SubscriptionStatus::CANCELLED) {
            $subscription->status = SubscriptionStatus::ACTIVE;
            $subscription->cancelled_at = null;
            $subscription->started_at = $subscription->started_at ?? now();
            if ($subscription->next_billing_at === null) {
                $subscription->next_billing_at = $this->resolveInitialNextBilling((int) $subscription->due_day)->toDateString();
            }
        }

        $subscription->save();

        $client = $subscription->client ?? Client::query()->find($subscription->client_id);
        if ($client && (int) $client->plan_id !== (int) $plan->getKey()) {
            $client->forceFill(['plan_id' => $plan->getKey()])->save();
        }

        return $subscription->refresh()->load(['client', 'plan']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinanceSubscription $subscription, array $data): FinanceSubscription
    {
        if (array_key_exists('next_billing_at', $data)) {
            $subscription->next_billing_at = $data['next_billing_at']
                ? CarbonImmutable::parse((string) $data['next_billing_at'])->toDateString()
                : null;
        }

        if (array_key_exists('due_day', $data)) {
            $subscription->due_day = min(max((int) $data['due_day'], 1), 28);
        }

        if (array_key_exists('block_on_overdue', $data)) {
            $subscription->block_on_overdue = (bool) $data['block_on_overdue'];
        }

        if (array_key_exists('block_after_days', $data)) {
            $subscription->block_after_days = (int) $data['block_after_days'];
        }

        $subscription->save();

        return $subscription->refresh()->load(['client', 'plan']);
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

        event(new SubscriptionCanceled($subscription));

        return $subscription->refresh()->load(['client', 'plan']);
    }

    /**
     * Reativa a assinatura e libera dispositivos mesmo com cobranças em aberto.
     */
    public function reactivate(FinanceSubscription $subscription): FinanceSubscription
    {
        if ($subscription->status === SubscriptionStatus::ACTIVE) {
            $this->delinquency->unsuspendClient((int) $subscription->client_id, force: true);

            return $subscription->refresh()->load(['client', 'plan']);
        }

        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->cancelled_at = null;
        if ($subscription->next_billing_at === null || $subscription->next_billing_at->isPast()) {
            $subscription->next_billing_at = $this->resolveInitialNextBilling((int) $subscription->due_day)->toDateString();
        }
        $subscription->save();

        $this->delinquency->unsuspendClient((int) $subscription->client_id, force: true);

        event(new SubscriptionCreated($subscription));

        return $subscription->refresh()->load(['client', 'plan']);
    }

    /**
     * @return Collection<int, FinanceSubscription>
     */
    public function dueForBilling(?CarbonImmutable $on = null)
    {
        $leadDays = max(0, (int) config('finance.days_before_due', 0));
        $date = ($on ?? CarbonImmutable::today())->addDays($leadDays)->toDateString();

        return FinanceSubscription::query()
            ->with(['client', 'plan'])
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->whereDate('next_billing_at', '<=', $date)
            ->orderBy('id')
            ->get();
    }

    public function advanceBillingDates(FinanceSubscription $subscription): FinanceSubscription
    {
        $periodicity = $subscription->plan_periodicity
            ?? $subscription->plan?->periodicity
            ?? BillingPeriodicity::MONTHLY;

        $from = $subscription->next_billing_at
            ? CarbonImmutable::parse($subscription->next_billing_at)
            : CarbonImmutable::today();

        $subscription->last_billed_at = $from->toDateString();
        $subscription->next_billing_at = NextBillingDate::advance($from, $periodicity)->toDateString();
        $subscription->save();

        return $subscription;
    }

    public function currentForClient(Client $client): ?FinanceSubscription
    {
        return FinanceSubscription::query()
            ->where('client_id', $client->getKey())
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'past_due' THEN 1 WHEN 'suspended' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->first();
    }

    private function assertNoOtherSubscription(Client $client): void
    {
        $exists = FinanceSubscription::query()
            ->where('client_id', $client->getKey())
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'client_id' => ['Este cliente já possui uma assinatura ativa.'],
            ]);
        }
    }

    /**
     * @param  mixed  $explicit
     */
    private function resolveInitialNextBilling(int $dueDay, $explicit = null): CarbonImmutable
    {
        if ($explicit) {
            return CarbonImmutable::parse((string) $explicit)->startOfDay();
        }

        $dueDay = min(max($dueDay, 1), 28);
        $base = CarbonImmutable::now()->startOfMonth()->day($dueDay);

        if ($base->isPast()) {
            $base = $base->addMonthNoOverflow();
        }

        return $base;
    }
}
