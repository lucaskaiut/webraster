<?php

namespace App\Modules\Finance\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            'billings', 'receivables' => $this->billings($filters),
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
    public function billings(array $filters = []): Collection
    {
        return $this->dateScoped(FinanceBilling::query()->with(['client', 'subscription']), 'due_at', $filters)
            ->orderBy('due_at')
            ->get()
            ->map(fn (FinanceBilling $b) => [
                'id' => $b->uuid,
                'code' => $b->code,
                'status' => $b->status?->value,
                'client' => $b->client?->name,
                'amount_cents' => $b->totalCents(),
                'due_at' => $b->due_at?->toDateString(),
                'paid_at' => $b->paid_at?->toIso8601String(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function delinquency(array $filters = []): Collection
    {
        return $this->dateScoped(
            FinanceBilling::query()
                ->with(['client', 'subscription'])
                ->where('status', BillingStatus::OVERDUE->value),
            'due_at',
            $filters,
        )
            ->orderBy('due_at')
            ->get()
            ->map(fn (FinanceBilling $b) => [
                'id' => $b->uuid,
                'code' => $b->code,
                'client' => $b->client?->name,
                'client_id' => $b->client?->uuid,
                'amount_cents' => $b->totalCents(),
                'due_at' => $b->due_at?->toDateString(),
                'days_overdue' => $b->due_at ? now()->startOfDay()->diffInDays($b->due_at) : null,
                'block_after_days' => $b->subscription?->block_after_days,
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function receipts(array $filters = []): Collection
    {
        return $this->dateScoped(
            FinanceBilling::query()
                ->with('client')
                ->where('status', BillingStatus::PAID->value),
            'paid_at',
            $filters,
        )
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (FinanceBilling $b) => [
                'id' => $b->uuid,
                'code' => $b->code,
                'client' => $b->client?->name,
                'paid_amount_cents' => $b->paid_amount_cents,
                'payment_method' => $b->payment_method?->value,
                'paid_at' => $b->paid_at?->toIso8601String(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function subscriptions(array $filters = []): Collection
    {
        $query = FinanceSubscription::query()->with(['client', 'plan']);

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
                'plan_name' => $s->plan_name,
                'plan_periodicity' => $s->plan_periodicity?->value,
                'next_billing_at' => $s->next_billing_at?->toDateString(),
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
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Model>
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
