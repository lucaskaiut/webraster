<?php

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\SubscriptionService;
use Illuminate\Console\Command;

class SuspendExpiredSubscriptionsCommand extends Command
{
    protected $signature = 'billing:suspend-expired-subscriptions';

    protected $description = 'Suspende assinaturas inadimplentes e encerra cortesias com prazo vencido';

    public function handle(BillingService $billing, SubscriptionService $subscriptions): int
    {
        $expiredComplimentary = $subscriptions->expireComplimentarySubscriptions();

        foreach ($expiredComplimentary as $subscription) {
            $this->line("Subscription {$subscription->uuid}: cortesia expirada");
        }

        $this->info('Cortesias expiradas: '.count($expiredComplimentary));

        $suspended = $billing->suspendEligibleSubscriptions();

        foreach ($suspended as $subscription) {
            $this->line("Subscription {$subscription->uuid} suspensa");
        }

        $this->info('Assinaturas suspensas: '.count($suspended));

        return self::SUCCESS;
    }
}
