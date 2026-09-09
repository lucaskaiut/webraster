<?php

namespace App\Modules\Finance\Console\Commands;

use App\Modules\Finance\Enums\SubscriptionStatus;
use App\Modules\Finance\Events\SubscriptionRenewed;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Services\FinanceReceivableService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateReceivablesCommand extends Command
{
    protected $signature = 'finance:generate-receivables';

    protected $description = 'Gera cobranças para assinaturas financeiras com next_billing_at vencido';

    public function handle(FinanceReceivableService $receivables): int
    {
        $generated = 0;
        $today = now()->toDateString();

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($receivables, $today, &$generated): void {
            TenantContext::set($tenant);

            FinanceSubscription::query()
                ->with('contract')
                ->where('status', SubscriptionStatus::ACTIVE->value)
                ->whereDate('next_billing_at', '<=', $today)
                ->orderBy('id')
                ->chunkById(50, function ($subscriptions) use ($receivables, &$generated): void {
                    foreach ($subscriptions as $subscription) {
                        /** @var FinanceSubscription $subscription */
                        $contract = $subscription->contract;
                        if ($contract === null) {
                            continue;
                        }

                        DB::transaction(function () use ($subscription, $contract, $receivables, &$generated): void {
                            $due = $subscription->next_billing_at;
                            $receivables->generateForContract($contract, $due ? \Carbon\Carbon::parse($due) : null);

                            $months = $subscription->periodicity?->months() ?: 1;
                            $subscription->last_billing_at = $subscription->next_billing_at;
                            $subscription->next_billing_at = \Carbon\CarbonImmutable::parse($subscription->next_billing_at)
                                ->addMonths($months)
                                ->toDateString();
                            $subscription->save();

                            event(new SubscriptionRenewed($subscription));
                            $generated++;
                        });
                    }
                });
        });

        TenantContext::forget();

        $this->info("Cobranças geradas: {$generated}");

        return self::SUCCESS;
    }
}
