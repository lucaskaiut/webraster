<?php

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Services\BillingService;
use Illuminate\Console\Command;

class SuspendExpiredSubscriptionsCommand extends Command
{
    protected $signature = 'billing:suspend-expired-subscriptions';

    protected $description = 'Suspende assinaturas com cobranças vencidas ou inadimplência prolongada';

    public function handle(BillingService $billing): int
    {
        $suspended = $billing->suspendEligibleSubscriptions();

        foreach ($suspended as $subscription) {
            $this->line("Subscription {$subscription->uuid} suspensa");
        }

        $this->info('Assinaturas suspensas: '.count($suspended));

        return self::SUCCESS;
    }
}
