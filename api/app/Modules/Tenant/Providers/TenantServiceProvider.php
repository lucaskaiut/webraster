<?php

namespace App\Modules\Tenant\Providers;

use App\Modules\Tenant\Resolution\Contracts\TenantResolverInterface;
use App\Modules\Tenant\Resolution\Strategies\ApiTokenStrategy;
use App\Modules\Tenant\Resolution\Strategies\AuthenticatedUserStrategy;
use App\Modules\Tenant\Resolution\Strategies\RefererStrategy;
use App\Modules\Tenant\Resolution\TenantResolver;
use App\Modules\Tenant\Support\CurrentTenant;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);

        $this->app->bind(TenantResolverInterface::class, TenantResolver::class);

        $this->app->bind(TenantResolver::class, function (Application $app): TenantResolver {
            return new TenantResolver(
                [
                    $app->make(AuthenticatedUserStrategy::class),
                    $app->make(ApiTokenStrategy::class),
                    $app->make(RefererStrategy::class),
                ],
                $app->make(CurrentTenant::class),
            );
        });
    }
}
