<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\ContractStatus;
use App\Modules\Finance\Enums\ReceivableStatus;
use App\Modules\Finance\Enums\SubscriptionStatus;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Finance\Models\FinanceReceivable;
use App\Modules\Finance\Models\FinanceSubscription;
use Carbon\CarbonImmutable;

class FinanceDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $activeContracts = FinanceContract::query()
            ->where('status', ContractStatus::ACTIVE->value)
            ->get(['id', 'client_id', 'amount_cents', 'discount_cents', 'periodicity']);

        $mrrCents = 0;
        foreach ($activeContracts as $contract) {
            $net = $contract->netAmountCents();
            $months = $contract->periodicity?->months() ?: 1;
            $mrrCents += (int) round($net / $months);
        }

        $activeClientIds = $activeContracts->pluck('client_id')->unique()->filter()->values();

        $delinquentClientIds = FinanceReceivable::query()
            ->where('status', ReceivableStatus::OVERDUE->value)
            ->distinct()
            ->pluck('client_id');

        $monthRevenueReceived = (int) FinanceReceivable::query()
            ->where('status', ReceivableStatus::RECEIVED->value)
            ->whereBetween('paid_at', [
                CarbonImmutable::parse($monthStart)->startOfDay(),
                CarbonImmutable::parse($monthEnd)->endOfDay(),
            ])
            ->sum('paid_amount_cents');

        $monthExpected = (int) FinanceReceivable::query()
            ->whereBetween('due_at', [$monthStart, $monthEnd])
            ->whereNotIn('status', [
                ReceivableStatus::CANCELLED->value,
                ReceivableStatus::REFUNDED->value,
            ])
            ->selectRaw('COALESCE(SUM(amount_cents - discount_cents + fine_cents + interest_cents), 0) as total')
            ->value('total');

        $openAmount = (int) FinanceReceivable::query()
            ->whereIn('status', [
                ReceivableStatus::PENDING->value,
                ReceivableStatus::AWAITING_PAYMENT->value,
                ReceivableStatus::OVERDUE->value,
            ])
            ->selectRaw('COALESCE(SUM(amount_cents - discount_cents + fine_cents + interest_cents), 0) as total')
            ->value('total');

        $activeSubscriptions = FinanceSubscription::query()
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->count();

        return [
            'mrr_cents' => $mrrCents,
            'mrr' => $this->formatMoney($mrrCents),
            'arr_cents' => $mrrCents * 12,
            'arr' => $this->formatMoney($mrrCents * 12),
            'active_clients' => $activeClientIds->count(),
            'active_contracts' => $activeContracts->count(),
            'active_subscriptions' => $activeSubscriptions,
            'delinquent_clients' => $delinquentClientIds->count(),
            'month_revenue_received_cents' => $monthRevenueReceived,
            'month_revenue_received' => $this->formatMoney($monthRevenueReceived),
            'month_expected_cents' => (int) $monthExpected,
            'month_expected' => $this->formatMoney((int) $monthExpected),
            'open_amount_cents' => (int) $openAmount,
            'open_amount' => $this->formatMoney((int) $openAmount),
        ];
    }

    private function formatMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
