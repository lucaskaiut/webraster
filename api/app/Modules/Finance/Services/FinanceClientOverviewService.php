<?php

namespace App\Modules\Finance\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use Illuminate\Support\Collection;

class FinanceClientOverviewService
{
    /**
     * @return array{
     *     plan: mixed,
     *     subscription: ?FinanceSubscription,
     *     open_billing: ?FinanceBilling,
     *     billings: Collection<int, FinanceBilling>
     * }
     */
    public function forClient(Client $client): array
    {
        $client->loadMissing('plan');

        $subscription = FinanceSubscription::query()
            ->with(['plan', 'client'])
            ->where('client_id', $client->getKey())
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'past_due' THEN 1 WHEN 'suspended' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->first();

        $openBilling = FinanceBilling::query()
            ->with(['client', 'subscription'])
            ->where('client_id', $client->getKey())
            ->whereIn('status', [
                BillingStatus::PENDING->value,
                BillingStatus::AWAITING_PAYMENT->value,
                BillingStatus::OVERDUE->value,
            ])
            ->orderByRaw("CASE status WHEN 'awaiting_payment' THEN 0 WHEN 'overdue' THEN 1 ELSE 2 END")
            ->orderBy('due_at')
            ->first();

        $billings = FinanceBilling::query()
            ->with(['client', 'subscription'])
            ->where('client_id', $client->getKey())
            ->orderByDesc('due_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return [
            'plan' => $client->plan,
            'subscription' => $subscription,
            'open_billing' => $openBilling,
            'billings' => $billings,
        ];
    }
}
