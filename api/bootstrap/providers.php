<?php

use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Chat\Providers\ChatServiceProvider;
use App\Modules\Client\Providers\ClientServiceProvider;
use App\Modules\Crm\Providers\CrmServiceProvider;
use App\Modules\Finance\Providers\FinanceServiceProvider;
use App\Modules\Tenant\Providers\TenantServiceProvider;
use App\Modules\Tracking\Providers\TrackingServiceProvider;
use App\Modules\VehicleData\Providers\VehicleDataServiceProvider;
use App\Modules\Webhook\Providers\WebhookServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    ChatServiceProvider::class,
    ClientServiceProvider::class,
    CrmServiceProvider::class,
    FinanceServiceProvider::class,
    TenantServiceProvider::class,
    TrackingServiceProvider::class,
    VehicleDataServiceProvider::class,
    WebhookServiceProvider::class,
];
