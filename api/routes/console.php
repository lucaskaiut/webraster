<?php

use App\Modules\Alert\Jobs\CheckOfflineDevicesJob;
use App\Modules\Billing\Console\Commands\CheckInvoiceStatusCommand;
use App\Modules\Billing\Console\Commands\GenerateInvoicesCommand;
use App\Modules\Billing\Console\Commands\SuspendExpiredSubscriptionsCommand;
use App\Modules\ContractEvidence\Console\Commands\ReconcileContractEvidenceCommand;
use App\Modules\Finance\Console\Commands\GenerateBillingsCommand;
use App\Modules\Finance\Console\Commands\MarkOverdueBillingsCommand;
use App\Modules\Finance\Console\Commands\NotifyDueSoonCommand;
use App\Modules\Finance\Console\Commands\ProcessDelinquencyCommand;
use App\Modules\Notification\Jobs\CheckPushReceiptsJob;
use App\Modules\ServiceOrder\Console\Commands\SendServiceOrderRemindersCommand;
use App\Modules\Tracking\Console\Commands\PruneTrackingDataCommand;
use App\Modules\Tracking\Jobs\DispatchPendingTraccarEventsJob;
use App\Modules\Tracking\Jobs\SyncTraccarDevicesJob;
use App\Modules\Tracking\Jobs\SyncTraccarPositionsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cobrança do umbrella aos filhos: só com billing.enabled (ver config/billing.php).
$billingEnabled = fn (): bool => (bool) config('billing.enabled');

Schedule::command(GenerateInvoicesCommand::class)->hourly()->when($billingEnabled);
Schedule::command(CheckInvoiceStatusCommand::class)->everyFifteenMinutes()->when($billingEnabled);
Schedule::command(SuspendExpiredSubscriptionsCommand::class)->daily()->when($billingEnabled);
Schedule::job(new CheckOfflineDevicesJob)->everyMinute();

Schedule::command(GenerateBillingsCommand::class)->hourly();
Schedule::command(MarkOverdueBillingsCommand::class)->hourly();
Schedule::command(ProcessDelinquencyCommand::class)->daily();
Schedule::command(NotifyDueSoonCommand::class)->daily();

Schedule::command(SendServiceOrderRemindersCommand::class)->everyMinute()->withoutOverlapping();

Schedule::command(ReconcileContractEvidenceCommand::class)->everyFiveMinutes()->withoutOverlapping();

Schedule::job(new DispatchPendingTraccarEventsJob)->everyMinute()->withoutOverlapping();
Schedule::job(new SyncTraccarPositionsJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn (): bool => (bool) config('traccar.enabled') && (bool) config('traccar.sync_enabled'));

Schedule::job(new SyncTraccarDevicesJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->when(fn (): bool => (bool) config('traccar.enabled') && (bool) config('traccar.sync_enabled'));

Schedule::job(new CheckPushReceiptsJob)
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->when(fn (): bool => (bool) config('notification.push.enabled'));

/*
| Retenção de dados — as tabelas dos relatórios de posição (gps_positions,
| alerts, device_command_logs) são preservadas por no mínimo 6 meses e não
| entram na limpeza. Ver config/pruning.php.
*/
Schedule::command(PruneTrackingDataCommand::class)->dailyAt('04:30')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->dailyAt('04:45')->withoutOverlapping();
