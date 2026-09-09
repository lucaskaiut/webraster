<?php

namespace App\Modules\Finance\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Enums\ReceivableStatus;
use App\Modules\Finance\Enums\SubscriptionStatus;
use App\Modules\Finance\Models\FinanceReceivable;
use App\Modules\Finance\Models\FinanceSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FinanceReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, mixed>|array<string, mixed>
     */
    public function report(string $type, array $filters = []): Collection|array
    {
        return match ($type) {
            'receivables' => $this->receivables($filters),
            'delinquency' => $this->delinquency($filters),
            'receipts' => $this->receipts($filters),
            'subscriptions' => $this->subscriptions($filters),
            'blocked_clients' => $this->blockedClients($filters),
            default => throw ValidationException::withMessages([
                'type' => ['Tipo de relatório inválido.'],
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function receivables(array $filters = []): Collection
    {
        return $this->dateScoped(FinanceReceivable::query()->with(['client', 'contract']), 'due_at', $filters)
            ->orderBy('due_at')
            ->get()
            ->map(fn (FinanceReceivable $r) => [
                'id' => $r->uuid,
                'code' => $r->code,
                'status' => $r->status?->value,
                'client' => $r->client?->name,
                'amount_cents' => $r->totalCents(),
                'due_at' => $r->due_at?->toDateString(),
                'paid_at' => $r->paid_at?->toIso8601String(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function delinquency(array $filters = []): Collection
    {
        return $this->dateScoped(
            FinanceReceivable::query()
                ->with(['client', 'contract'])
                ->where('status', ReceivableStatus::OVERDUE->value),
            'due_at',
            $filters,
        )
            ->orderBy('due_at')
            ->get()
            ->map(fn (FinanceReceivable $r) => [
                'id' => $r->uuid,
                'code' => $r->code,
                'client' => $r->client?->name,
                'client_id' => $r->client?->uuid,
                'amount_cents' => $r->totalCents(),
                'due_at' => $r->due_at?->toDateString(),
                'days_overdue' => $r->due_at ? now()->startOfDay()->diffInDays($r->due_at) : null,
                'block_after_days' => $r->contract?->block_after_days,
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function receipts(array $filters = []): Collection
    {
        return $this->dateScoped(
            FinanceReceivable::query()
                ->with('client')
                ->where('status', ReceivableStatus::RECEIVED->value),
            'paid_at',
            $filters,
        )
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (FinanceReceivable $r) => [
                'id' => $r->uuid,
                'code' => $r->code,
                'client' => $r->client?->name,
                'paid_amount_cents' => $r->paid_amount_cents,
                'payment_method' => $r->payment_method?->value,
                'paid_at' => $r->paid_at?->toIso8601String(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function subscriptions(array $filters = []): Collection
    {
        $query = FinanceSubscription::query()->with(['client', 'contract']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FinanceSubscription $s) => [
                'id' => $s->uuid,
                'status' => $s->status?->value,
                'client' => $s->client?->name,
                'periodicity' => $s->periodicity?->value,
                'next_billing_at' => $s->next_billing_at?->toDateString(),
                'contract_code' => $s->contract?->code,
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function blockedClients(array $filters = []): Collection
    {
        $equipment = Equipment::query()
            ->with('vehicle.client')
            ->whereNotNull('billing_suspended_at')
            ->get();

        return $equipment
            ->groupBy(fn (Equipment $e) => $e->vehicle?->client_id)
            ->filter(fn ($group, $clientId) => filled($clientId))
            ->map(function (Collection $items) {
                /** @var Equipment $first */
                $first = $items->first();
                $client = $first->vehicle?->client;

                return [
                    'client_id' => $client?->uuid,
                    'client' => $client?->name,
                    'devices_suspended' => $items->count(),
                    'suspended_since' => $items->min('billing_suspended_at')?->toIso8601String(),
                ];
            })
            ->values();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     */
    private function dateScoped($query, string $column, array $filters)
    {
        if (! empty($filters['from'])) {
            $query->whereDate($column, '>=', CarbonImmutable::parse((string) $filters['from'])->toDateString());
        }

        if (! empty($filters['to'])) {
            $query->whereDate($column, '<=', CarbonImmutable::parse((string) $filters['to'])->toDateString());
        }

        return $query;
    }
}
