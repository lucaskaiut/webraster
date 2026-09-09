<?php

namespace App\Modules\Finance\Providers;

use App\Modules\Finance\Channels\EmailFinanceNotificationChannel;
use App\Modules\Finance\Console\Commands\GenerateReceivablesCommand;
use App\Modules\Finance\Console\Commands\MarkOverdueReceivablesCommand;
use App\Modules\Finance\Console\Commands\NotifyDueSoonCommand;
use App\Modules\Finance\Console\Commands\ProcessDelinquencyCommand;
use App\Modules\Finance\Contracts\DeviceSuspensionProvider;
use App\Modules\Finance\Contracts\FinanceNotificationChannel;
use App\Modules\Finance\Events\InvoiceCanceled;
use App\Modules\Finance\Events\InvoiceCreated;
use App\Modules\Finance\Events\InvoiceDueSoon;
use App\Modules\Finance\Events\InvoiceOverdue;
use App\Modules\Finance\Events\InvoicePaid;
use App\Modules\Finance\Events\SubscriptionCanceled;
use App\Modules\Finance\Events\SubscriptionCreated;
use App\Modules\Finance\Events\SubscriptionRenewed;
use App\Modules\Finance\Listeners\SendFinanceNotifications;
use App\Modules\Finance\Services\FinanceNotificationService;
use App\Modules\Finance\Services\TraccarDeviceSuspensionProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeviceSuspensionProvider::class, TraccarDeviceSuspensionProvider::class);

        $this->app->bind(FinanceNotificationChannel::class, EmailFinanceNotificationChannel::class);

        $this->app->singleton(FinanceNotificationService::class, function ($app) {
            return new FinanceNotificationService([
                $app->make(EmailFinanceNotificationChannel::class),
            ]);
        });
    }

    public function boot(): void
    {
        Event::listen(InvoiceCreated::class, [SendFinanceNotifications::class, 'handleInvoiceCreated']);
        Event::listen(InvoiceDueSoon::class, [SendFinanceNotifications::class, 'handleInvoiceDueSoon']);
        Event::listen(InvoiceOverdue::class, [SendFinanceNotifications::class, 'handleInvoiceOverdue']);
        Event::listen(InvoicePaid::class, [SendFinanceNotifications::class, 'handleInvoicePaid']);
        Event::listen(InvoiceCanceled::class, [SendFinanceNotifications::class, 'handleInvoiceCanceled']);
        Event::listen(SubscriptionCreated::class, [SendFinanceNotifications::class, 'handleSubscriptionCreated']);
        Event::listen(SubscriptionCanceled::class, [SendFinanceNotifications::class, 'handleSubscriptionCanceled']);
        Event::listen(SubscriptionRenewed::class, [SendFinanceNotifications::class, 'handleSubscriptionRenewed']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateReceivablesCommand::class,
                MarkOverdueReceivablesCommand::class,
                ProcessDelinquencyCommand::class,
                NotifyDueSoonCommand::class,
            ]);
        }
    }
}
