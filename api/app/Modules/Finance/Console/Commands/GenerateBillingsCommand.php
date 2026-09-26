<?php

namespace App\Modules\Finance\Console\Commands;

use App\Modules\Finance\Events\SubscriptionRenewed;
use App\Modules\Finance\Services\FinanceBillingService;
use App\Modules\Finance\Services\FinanceSubscriptionService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateBillingsCommand extends Command
{
    protected $signature = 'finance:generate-billings';

    protected $description = 'Gera cobranças para assinaturas financeiras dentro da janela de antecedência de next_billing_at';

    public function handle(
        FinanceBillingService $billings,
        FinanceSubscriptionService $subscriptions,
    ): int {
        $generated = 0;
        $today = now()->toDateString();

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($billings, $subscriptions, $today, &$generated): void {
            TenantContext::set($tenant);

            $due = $subscriptions->dueForBilling(CarbonImmutable::parse($today));

            foreach ($due as $subscription) {
                DB::transaction(function () use ($subscription, $billings, $subscriptions, &$generated): void {
                    $dueDate = $subscription->next_billing_at
                        ? Carbon::parse($subscription->next_billing_at)
                        : null;

                    $billings->generateForSubscription($subscription, $dueDate);
                    $subscriptions->advanceBillingDates($subscription);
                    event(new SubscriptionRenewed($subscription->fresh()));
                    $generated++;
                });
            }
        });

        TenantContext::forget();

        $this->info("Cobranças geradas: {$generated}");

        return self::SUCCESS;
    }
}
