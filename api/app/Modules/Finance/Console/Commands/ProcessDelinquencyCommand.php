<?php

namespace App\Modules\Finance\Console\Commands;

use App\Modules\Finance\Services\DelinquencyService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Console\Command;

class ProcessDelinquencyCommand extends Command
{
    protected $signature = 'finance:process-delinquency';

    protected $description = 'Processa inadimplência e suspende dispositivos conforme contratos';

    public function handle(DelinquencyService $delinquency): int
    {
        $total = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($delinquency, &$total): void {
            TenantContext::set($tenant);
            $total += $delinquency->process();
        });

        TenantContext::forget();

        $this->info("Clientes com suspensão processada: {$total}");

        return self::SUCCESS;
    }
}
