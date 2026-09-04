<?php

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\SubscriptionService;
use Illuminate\Console\Command;

class GenerateInvoicesCommand extends Command
{
    protected $signature = 'billing:generate-invoices';

    protected $description = 'Gera cobranças para assinaturas com next_billing_at vencido (recorrente com token quando disponível)';

    public function handle(SubscriptionService $subscriptions, BillingService $billing): int
    {
        $due = $subscriptions->dueForBilling();
        $generated = 0;
        $charged = 0;
        $fallback = 0;

        foreach ($due as $subscription) {
            $invoice = $billing->generateInvoice($subscription);
            $generated++;

            if (filled($invoice->external_id)) {
                $charged++;
                $this->line(
                    "Invoice {$invoice->uuid} cobrada automaticamente (recorrente) para subscription {$subscription->uuid}"
                );
            } elseif ($subscription->recurring && filled($subscription->credit_card_token)) {
                $fallback++;
                $this->warn(
                    "Invoice {$invoice->uuid} gerada sem captura (falha recorrente) para subscription {$subscription->uuid}"
                );
            } else {
                $this->line(
                    "Invoice {$invoice->uuid} gerada para subscription {$subscription->uuid}"
                );
            }
        }

        $this->info("Cobranças geradas: {$generated} (automáticas: {$charged}, fallback manual: {$fallback})");

        return self::SUCCESS;
    }
}
