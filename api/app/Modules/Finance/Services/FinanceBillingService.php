<?php

namespace App\Modules\Finance\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Events\InvoiceCanceled;
use App\Modules\Finance\Events\InvoiceCreated;
use App\Modules\Finance\Events\InvoiceOverdue;
use App\Modules\Finance\Events\InvoicePaid;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceBillingEvent;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceBillingService
{
    public function __construct(
        private readonly DelinquencyService $delinquency,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['client', 'subscription.plan'])
            ->orderByDesc('due_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function generateForSubscription(
        FinanceSubscription $subscription,
        ?Carbon $dueDate = null,
    ): FinanceBilling {
        $due = $dueDate
            ? CarbonImmutable::parse($dueDate)->startOfDay()
            : $this->defaultDueDate($subscription);

        $billing = DB::transaction(function () use ($subscription, $due) {
            $amount = (int) ($subscription->plan_price_cents ?? $subscription->plan?->amount_cents ?? 0);

            $billing = FinanceBilling::query()->create([
                'number' => $this->nextNumber((int) ($subscription->tenant_id ?: TenantContext::tenantId())),
                'subscription_id' => $subscription->getKey(),
                'client_id' => $subscription->client_id,
                'status' => BillingStatus::PENDING,
                'amount_cents' => $amount,
                'discount_cents' => 0,
                'fine_cents' => 0,
                'interest_cents' => 0,
                'due_at' => $due->toDateString(),
                'description' => sprintf(
                    'Cobrança %s — %s',
                    $subscription->plan_name ?: 'Assinatura',
                    $due->format('m/Y'),
                ),
            ]);

            $this->recordEvent($billing, 'created', null, BillingStatus::PENDING);

            return $billing;
        });

        event(new InvoiceCreated($billing));

        return $billing->load(['client', 'subscription.plan']);
    }

    public function markOverdue(): int
    {
        $today = now()->toDateString();
        $count = 0;

        FinanceBilling::query()
            ->whereIn('status', [
                BillingStatus::PENDING->value,
                BillingStatus::AWAITING_PAYMENT->value,
            ])
            ->whereDate('due_at', '<', $today)
            ->orderBy('id')
            ->chunkById(100, function ($billings) use (&$count): void {
                foreach ($billings as $billing) {
                    /** @var FinanceBilling $billing */
                    $from = $billing->status;
                    $billing->status = BillingStatus::OVERDUE;
                    $billing->save();
                    $this->recordEvent($billing, 'marked_overdue', $from, BillingStatus::OVERDUE);
                    event(new InvoiceOverdue($billing));
                    $count++;
                }
            });

        return $count;
    }

    public function markPaid(FinanceBilling $billing, ?int $paidAmount = null): FinanceBilling
    {
        if ($billing->status === BillingStatus::PAID) {
            return $billing;
        }

        if (in_array($billing->status, [BillingStatus::CANCELLED, BillingStatus::REFUNDED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível liquidar uma cobrança cancelada ou estornada.'],
            ]);
        }

        $from = $billing->status;
        $billing->status = BillingStatus::PAID;
        $billing->paid_at = now();
        $billing->paid_amount_cents = $paidAmount ?? $billing->totalCents();
        $billing->save();

        $this->recordEvent($billing, 'paid', $from, BillingStatus::PAID, [
            'paid_amount_cents' => $billing->paid_amount_cents,
        ]);

        event(new InvoicePaid($billing));

        $this->delinquency->unsuspendClient((int) $billing->client_id);

        return $billing->refresh()->load(['client', 'subscription.plan']);
    }

    public function cancel(FinanceBilling $billing, ?User $actor = null): FinanceBilling
    {
        if (! $billing->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['Esta cobrança não pode ser cancelada.'],
            ]);
        }

        $from = $billing->status;
        $billing->status = BillingStatus::CANCELLED;
        $billing->cancelled_at = now();
        $billing->save();

        $this->recordEvent($billing, 'cancelled', $from, BillingStatus::CANCELLED, null, $actor);
        event(new InvoiceCanceled($billing));

        return $billing->refresh()->load(['client', 'subscription.plan']);
    }

    public function refund(FinanceBilling $billing, ?User $actor = null): FinanceBilling
    {
        if ($billing->status !== BillingStatus::PAID) {
            throw ValidationException::withMessages([
                'status' => ['Somente cobranças pagas podem ser estornadas.'],
            ]);
        }

        $from = $billing->status;
        $billing->status = BillingStatus::REFUNDED;
        $billing->save();

        $this->recordEvent($billing, 'refunded', $from, BillingStatus::REFUNDED, null, $actor);

        return $billing->refresh()->load(['client', 'subscription.plan']);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function recordEvent(
        FinanceBilling $billing,
        string $action,
        BillingStatus|string|null $fromStatus,
        BillingStatus|string|null $toStatus,
        ?array $meta = null,
        ?User $actor = null,
    ): FinanceBillingEvent {
        return FinanceBillingEvent::query()->create([
            'billing_id' => $billing->getKey(),
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'from_status' => $fromStatus instanceof BillingStatus ? $fromStatus->value : $fromStatus,
            'to_status' => $toStatus instanceof BillingStatus ? $toStatus->value : $toStatus,
            'meta' => $meta,
        ]);
    }

    public function resolveClientId(string $uuidOrId): ?int
    {
        if (ctype_digit($uuidOrId)) {
            return (int) $uuidOrId;
        }

        return Client::query()->where('uuid', $uuidOrId)->value('id');
    }

    public function nextNumber(int $tenantId): int
    {
        $max = FinanceBilling::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('number');

        return ((int) $max) + 1;
    }

    private function defaultDueDate(FinanceSubscription $subscription): CarbonImmutable
    {
        if ($subscription->next_billing_at) {
            return CarbonImmutable::parse($subscription->next_billing_at)->startOfDay();
        }

        $dueDay = min(max((int) $subscription->due_day, 1), 28);
        $base = now()->startOfMonth()->day($dueDay);

        if ($base->isPast()) {
            $base = $base->addMonth();
        }

        return CarbonImmutable::parse($base);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters)
    {
        $query = FinanceBilling::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['client_id'])) {
            $clientId = is_numeric($filters['client_id'])
                ? (int) $filters['client_id']
                : $this->resolveClientId((string) $filters['client_id']);

            if ($clientId) {
                $query->where('client_id', $clientId);
            }
        }

        if (! empty($filters['subscription_id'])) {
            $subscriptionId = $filters['subscription_id'];
            if (! is_numeric($subscriptionId)) {
                $subscriptionId = FinanceSubscription::query()->where('uuid', $subscriptionId)->value('id');
            }
            if ($subscriptionId) {
                $query->where('subscription_id', $subscriptionId);
            }
        }

        if (! empty($filters['due_from'])) {
            $query->whereDate('due_at', '>=', CarbonImmutable::parse((string) $filters['due_from'])->toDateString());
        }

        if (! empty($filters['due_to'])) {
            $query->whereDate('due_at', '<=', CarbonImmutable::parse((string) $filters['due_to'])->toDateString());
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('description', 'like', "%{$search}%");

                if (preg_match('/^BILL-?0*(\d+)$/i', $search, $matches)) {
                    $builder->orWhere('number', (int) $matches[1]);
                } elseif (ctype_digit($search)) {
                    $builder->orWhere('number', (int) $search);
                }

                $builder->orWhereHas('client', function ($clientQuery) use ($search): void {
                    $clientQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        return $query;
    }
}
