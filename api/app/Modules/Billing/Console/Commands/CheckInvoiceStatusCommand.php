<?php

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Services\BillingService;
use Illuminate\Console\Command;

class CheckInvoiceStatusCommand extends Command
{
    protected $signature = 'billing:check-invoice-status';

    protected $description = 'Consulta o gateway e expira faturas locais vencidas';

    public function handle(BillingService $billing): int
    {
        $localExpired = $billing->expireOverdueLocalInvoices();
        $reconciled = $billing->reconcileUnconfirmedPaidInvoices();
        $invoices = $billing->openInvoices();
        $updated = 0;

        foreach ($invoices as $invoice) {
            $synced = $billing->syncInvoiceStatus($invoice);
            $updated++;
            $this->line("Invoice {$synced->uuid} → {$synced->status->value}");
        }

        $this->info("Invoices verificadas: {$updated}");
        $this->info("Faturas locais expiradas: {$localExpired}");
        $this->info("Faturas PAID reconciliadas: {$reconciled}");

        return self::SUCCESS;
    }
}
