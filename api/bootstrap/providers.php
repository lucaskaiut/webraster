<?php

use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Client\Providers\ClientServiceProvider;
use App\Modules\Tenant\Providers\TenantServiceProvider;
use App\Modules\Webhook\Providers\WebhookServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    ClientServiceProvider::class,
    TenantServiceProvider::class,
    WebhookServiceProvider::class,
];
