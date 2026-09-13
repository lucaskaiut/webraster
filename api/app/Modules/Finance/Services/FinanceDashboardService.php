<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Shared\Subscription\Enums\SubscriptionStatus;
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

        $activeSubscriptions = FinanceSubscription::query()
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->get(['id', 'client_id', 'plan_price_cents', 'plan_periodicity']);

        $mrrCents = 0;
        foreach ($activeSubscriptions as $subscription) {
            $net = (int) ($subscription->plan_price_cents ?? 0);
            $months = $subscription->plan_periodicity?->months() ?: 1;
            $mrrCents += (int) round($net / $months);
        }

        $activeClientIds = $activeSubscriptions->pluck('client_id')->unique()->filter()->values();

        $delinquentClientIds = FinanceBilling::query()
            ->where('status', BillingStatus::OVERDUE->value)
            ->distinct()
            ->pluck('client_id');

        $monthRevenuePaid = (int) FinanceBilling::query()
            ->where('status', BillingStatus::PAID->value)
            ->whereBetween('paid_at', [
                CarbonImmutable::parse($monthStart)->startOfDay(),
                CarbonImmutable::parse($monthEnd)->endOfDay(),
            ])
            ->sum('paid_amount_cents');

        $monthExpected = (int) FinanceBilling::query()
            ->whereBetween('due_at', [$monthStart, $monthEnd])
            ->whereNotIn('status', [
                BillingStatus::CANCELLED->value,
                BillingStatus::REFUNDED->value,
            ])
            ->selectRaw('COALESCE(SUM(amount_cents - discount_cents + fine_cents + interest_cents), 0) as total')
            ->value('total');

        $openAmount = (int) FinanceBilling::query()
            ->whereIn('status', [
                BillingStatus::PENDING->value,
                BillingStatus::AWAITING_PAYMENT->value,
                BillingStatus::OVERDUE->value,
            ])
            ->selectRaw('COALESCE(SUM(amount_cents - discount_cents + fine_cents + interest_cents), 0) as total')
            ->value('total');

        return [
            'mrr_cents' => $mrrCents,
            'mrr' => $this->formatMoney($mrrCents),
            'arr_cents' => $mrrCents * 12,
            'arr' => $this->formatMoney($mrrCents * 12),
            'active_clients' => $activeClientIds->count(),
            'active_subscriptions' => $activeSubscriptions->count(),
            'delinquent_clients' => $delinquentClientIds->count(),
            'month_revenue_received_cents' => $monthRevenuePaid,
            'month_revenue_received' => $this->formatMoney($monthRevenuePaid),
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
