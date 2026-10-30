<?php

namespace App\Modules\ServiceOrder\Console\Commands;

use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Services\ServiceOrderNotificationService;
use App\Modules\Tenant\Enums\TenantModule;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Console\Command;

class SendServiceOrderRemindersCommand extends Command
{
    protected $signature = 'service-orders:send-reminders';

    protected $description = 'Envia ao técnico o lembrete dos atendimentos que começam em breve';

    public function handle(ServiceOrderNotificationService $notifications): int
    {
        $now = now();
        $limit = $now->copy()->addMinutes(max(1, (int) config('scheduling.reminder_minutes', 30)));
        $count = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($now, $limit, $notifications, &$count): void {
            if (! $tenant->isActive() || ! $tenant->hasModule(TenantModule::SERVICE_ORDERS)) {
                return;
            }

            TenantContext::set($tenant);

            ServiceOrder::query()
                ->with('client')
                ->whereNotNull('technician_id')
                ->whereNull('reminder_sent_at')
                ->whereIn('status', [ServiceOrderStatus::OPEN->value, ServiceOrderStatus::IN_PROGRESS->value])
                ->whereBetween('scheduled_start_at', [$now, $limit])
                ->orderBy('id')
                ->chunkById(100, function ($orders) use ($notifications, &$count): void {
                    foreach ($orders as $order) {
                        // Marca antes de enviar: uma falha no push não deve gerar lembretes repetidos.
                        $order->forceFill(['reminder_sent_at' => now()])->saveQuietly();
                        $notifications->notifyReminder($order);
                        $count++;
                    }
                });
        });

        TenantContext::forget();

        $this->info("Lembretes de OS enviados: {$count}");

        return self::SUCCESS;
    }
}
