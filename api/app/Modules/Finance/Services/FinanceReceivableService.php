<?php

namespace App\Modules\Finance\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Enums\ReceivableStatus;
use App\Modules\Finance\Events\InvoiceCanceled;
use App\Modules\Finance\Events\InvoiceCreated;
use App\Modules\Finance\Events\InvoiceOverdue;
use App\Modules\Finance\Events\InvoicePaid;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Finance\Models\FinanceReceivable;
use App\Modules\Finance\Models\FinanceReceivableEvent;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceReceivableService
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
            ->with(['client', 'contract.plan', 'subscription'])
            ->orderByDesc('due_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function generateForContract(FinanceContract $contract, ?Carbon $dueDate = null): FinanceReceivable
    {
        $due = $dueDate
            ? CarbonImmutable::parse($dueDate)->startOfDay()
            : $this->defaultDueDate($contract);

        $receivable = DB::transaction(function () use ($contract, $due) {
            $receivable = FinanceReceivable::query()->create([
                'number' => $this->nextNumber((int) ($contract->tenant_id ?: TenantContext::tenantId())),
                'contract_id' => $contract->getKey(),
                'subscription_id' => $contract->subscription?->getKey(),
                'client_id' => $contract->client_id,
                'status' => ReceivableStatus::PENDING,
                'amount_cents' => $contract->netAmountCents(),
                'discount_cents' => 0,
                'fine_cents' => 0,
                'interest_cents' => 0,
                'due_at' => $due->toDateString(),
                'description' => sprintf('Cobrança %s — %s', $contract->code, $due->format('m/Y')),
            ]);

            $this->recordEvent($receivable, 'created', null, ReceivableStatus::PENDING);

            return $receivable;
        });

        event(new InvoiceCreated($receivable));

        return $receivable->load(['client', 'contract.plan', 'subscription']);
    }

    public function markOverdue(): int
    {
        $today = now()->toDateString();
        $count = 0;

        FinanceReceivable::query()
            ->whereIn('status', [
                ReceivableStatus::PENDING->value,
                ReceivableStatus::AWAITING_PAYMENT->value,
            ])
            ->whereDate('due_at', '<', $today)
            ->orderBy('id')
            ->chunkById(100, function ($receivables) use (&$count): void {
                foreach ($receivables as $receivable) {
                    /** @var FinanceReceivable $receivable */
                    $from = $receivable->status;
                    $receivable->status = ReceivableStatus::OVERDUE;
                    $receivable->save();
                    $this->recordEvent($receivable, 'marked_overdue', $from, ReceivableStatus::OVERDUE);
                    event(new InvoiceOverdue($receivable));
                    $count++;
                }
            });

        return $count;
    }

    public function markReceived(FinanceReceivable $receivable, ?int $paidAmount = null): FinanceReceivable
    {
        if ($receivable->status === ReceivableStatus::RECEIVED) {
            return $receivable;
        }

        if (in_array($receivable->status, [ReceivableStatus::CANCELLED, ReceivableStatus::REFUNDED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível liquidar uma cobrança cancelada ou estornada.'],
            ]);
        }

        $from = $receivable->status;
        $receivable->status = ReceivableStatus::RECEIVED;
        $receivable->paid_at = now();
        $receivable->paid_amount_cents = $paidAmount ?? $receivable->totalCents();
        $receivable->save();

        $this->recordEvent($receivable, 'received', $from, ReceivableStatus::RECEIVED, [
            'paid_amount_cents' => $receivable->paid_amount_cents,
        ]);

        event(new InvoicePaid($receivable));

        $this->delinquency->unsuspendClient((int) $receivable->client_id);

        return $receivable->refresh()->load(['client', 'contract.plan', 'subscription']);
    }

    public function cancel(FinanceReceivable $receivable, ?User $actor = null): FinanceReceivable
    {
        if (! $receivable->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['Esta cobrança não pode ser cancelada.'],
            ]);
        }

        $from = $receivable->status;
        $receivable->status = ReceivableStatus::CANCELLED;
        $receivable->cancelled_at = now();
        $receivable->save();

        $this->recordEvent($receivable, 'cancelled', $from, ReceivableStatus::CANCELLED, null, $actor);
        event(new InvoiceCanceled($receivable));

        return $receivable->refresh()->load(['client', 'contract.plan', 'subscription']);
    }

    public function refund(FinanceReceivable $receivable, ?User $actor = null): FinanceReceivable
    {
        if ($receivable->status !== ReceivableStatus::RECEIVED) {
            throw ValidationException::withMessages([
                'status' => ['Somente cobranças recebidas podem ser estornadas.'],
            ]);
        }

        $from = $receivable->status;
        $receivable->status = ReceivableStatus::REFUNDED;
        $receivable->save();

        $this->recordEvent($receivable, 'refunded', $from, ReceivableStatus::REFUNDED, null, $actor);

        return $receivable->refresh()->load(['client', 'contract.plan', 'subscription']);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function recordEvent(
        FinanceReceivable $receivable,
        string $action,
        ReceivableStatus|string|null $fromStatus,
        ReceivableStatus|string|null $toStatus,
        ?array $meta = null,
        ?User $actor = null,
    ): FinanceReceivableEvent {
        return FinanceReceivableEvent::query()->create([
            'receivable_id' => $receivable->getKey(),
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'from_status' => $fromStatus instanceof ReceivableStatus ? $fromStatus->value : $fromStatus,
            'to_status' => $toStatus instanceof ReceivableStatus ? $toStatus->value : $toStatus,
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
        $max = FinanceReceivable::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('number');

        return ((int) $max) + 1;
    }

    private function defaultDueDate(FinanceContract $contract): CarbonImmutable
    {
        $dueDay = min(max((int) $contract->due_day, 1), 28);
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
        $query = FinanceReceivable::query();

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

        if (! empty($filters['contract_id'])) {
            $contractId = $filters['contract_id'];
            if (! is_numeric($contractId)) {
                $contractId = FinanceContract::query()->where('uuid', $contractId)->value('id');
            }
            if ($contractId) {
                $query->where('contract_id', $contractId);
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

                if (preg_match('/^REC-?0*(\d+)$/i', $search, $matches)) {
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
