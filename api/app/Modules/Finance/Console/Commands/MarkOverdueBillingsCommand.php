<?php

namespace App\Modules\Finance\Console\Commands;

use App\Modules\Finance\Services\FinanceBillingService;
use App\Modules\Tenant\Enums\TenantModule;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Console\Command;

class MarkOverdueBillingsCommand extends Command
{
    protected $signature = 'finance:mark-overdue';

    protected $description = 'Marca cobranças financeiras vencidas como overdue';

    public function handle(FinanceBillingService $billings): int
    {
        $total = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($billings, &$total): void {
            // Empresas inativas ou sem o módulo financeiro não geram cobranças nem avisos.
            if (! $tenant->isActive() || ! $tenant->hasModule(TenantModule::FINANCE)) {
                return;
            }

            TenantContext::set($tenant);
            $total += $billings->markOverdue();
        });

        TenantContext::forget();

        $this->info("Cobranças marcadas como vencidas: {$total}");

        return self::SUCCESS;
    }
}
