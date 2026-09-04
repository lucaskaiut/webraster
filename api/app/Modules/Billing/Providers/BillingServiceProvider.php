<?php

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Console\Commands\CheckInvoiceStatusCommand;
use App\Modules\Billing\Console\Commands\GenerateInvoicesCommand;
use App\Modules\Billing\Console\Commands\SuspendExpiredSubscriptionsCommand;
use App\Modules\Billing\Gateways\Asaas\AsaasClient;
use App\Modules\Billing\Gateways\AsaasCreditCardGateway;
use App\Modules\Billing\Gateways\MockPixGateway;
use App\Modules\Billing\Support\PaymentGatewayResolver;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayResolver::class);
        $this->app->singleton(MockPixGateway::class);
        $this->app->singleton(AsaasClient::class);
        $this->app->singleton(AsaasCreditCardGateway::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateInvoicesCommand::class,
                CheckInvoiceStatusCommand::class,
                SuspendExpiredSubscriptionsCommand::class,
            ]);
        }
    }
}
