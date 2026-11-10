<?php

namespace App\Modules\Finance\Console\Commands;

use App\Modules\Finance\Services\CollectionReminderService;
use App\Modules\Tenant\Enums\TenantModule;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Console\Command;

class SendCollectionRemindersCommand extends Command
{
    protected $signature = 'finance:send-collection-reminders';

    protected $description = 'Envia os avisos da régua de cobrança configurada por cada empresa';

    public function handle(CollectionReminderService $reminders): int
    {
        $total = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($reminders, &$total): void {
            // Empresas inativas ou sem o módulo financeiro não geram cobranças nem avisos.
            if (! $tenant->isActive() || ! $tenant->hasModule(TenantModule::FINANCE)) {
                return;
            }

            TenantContext::set($tenant);
            $total += $reminders->process();
        });

        TenantContext::forget();

        $this->info("Avisos da régua de cobrança enviados: {$total}");

        return self::SUCCESS;
    }
}
