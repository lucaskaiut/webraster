<?php

namespace App\Modules\Finance\Console\Commands;

use App\Modules\Finance\Enums\ReceivableStatus;
use App\Modules\Finance\Events\InvoiceDueSoon;
use App\Modules\Finance\Models\FinanceReceivable;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Console\Command;

class NotifyDueSoonCommand extends Command
{
    protected $signature = 'finance:notify-due-soon {--days=3 : Dias até o vencimento}';

    protected $description = 'Notifica cobranças que vencem em N dias';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $target = now()->addDays($days)->toDateString();
        $count = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($target, &$count): void {
            TenantContext::set($tenant);

            FinanceReceivable::query()
                ->whereIn('status', [
                    ReceivableStatus::PENDING->value,
                    ReceivableStatus::AWAITING_PAYMENT->value,
                ])
                ->whereDate('due_at', $target)
                ->orderBy('id')
                ->chunkById(100, function ($receivables) use (&$count): void {
                    foreach ($receivables as $receivable) {
                        event(new InvoiceDueSoon($receivable));
                        $count++;
                    }
                });
        });

        TenantContext::forget();

        $this->info("Notificações de vencimento disparadas: {$count}");

        return self::SUCCESS;
    }
}
