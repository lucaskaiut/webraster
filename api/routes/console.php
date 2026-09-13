<?php

use App\Modules\Alert\Jobs\CheckOfflineDevicesJob;
use App\Modules\Billing\Console\Commands\CheckInvoiceStatusCommand;
use App\Modules\Billing\Console\Commands\GenerateInvoicesCommand;
use App\Modules\Billing\Console\Commands\SuspendExpiredSubscriptionsCommand;
use App\Modules\Finance\Console\Commands\GenerateBillingsCommand;
use App\Modules\Finance\Console\Commands\MarkOverdueBillingsCommand;
use App\Modules\Finance\Console\Commands\NotifyDueSoonCommand;
use App\Modules\Finance\Console\Commands\ProcessDelinquencyCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(GenerateInvoicesCommand::class)->hourly();
Schedule::command(CheckInvoiceStatusCommand::class)->everyFifteenMinutes();
Schedule::command(SuspendExpiredSubscriptionsCommand::class)->daily();
Schedule::job(new CheckOfflineDevicesJob)->everyMinute();

Schedule::command(GenerateBillingsCommand::class)->daily();
Schedule::command(MarkOverdueBillingsCommand::class)->hourly();
Schedule::command(ProcessDelinquencyCommand::class)->daily();
Schedule::command(NotifyDueSoonCommand::class)->daily();
