<?php

namespace App\Modules\Finance\Console\Commands;

use App\Modules\Finance\Services\FinanceReceivableService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Console\Command;

class MarkOverdueReceivablesCommand extends Command
{
    protected $signature = 'finance:mark-overdue';

    protected $description = 'Marca cobranças financeiras vencidas como overdue';

    public function handle(FinanceReceivableService $receivables): int
    {
        $total = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($receivables, &$total): void {
            TenantContext::set($tenant);
            $total += $receivables->markOverdue();
        });

        TenantContext::forget();

        $this->info("Cobranças marcadas como vencidas: {$total}");

        return self::SUCCESS;
    }
}
