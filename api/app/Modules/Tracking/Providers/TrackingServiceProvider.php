<?php

namespace App\Modules\Tracking\Providers;

use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\Gateways\HttpTraccarGateway;
use App\Modules\Tracking\Gateways\NullTraccarGateway;
use Illuminate\Support\ServiceProvider;

class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TraccarGateway::class, function (): TraccarGateway {
            $http = $this->app->make(HttpTraccarGateway::class);

            return $http->isConfigured() ? $http : $this->app->make(NullTraccarGateway::class);
        });
    }
}
