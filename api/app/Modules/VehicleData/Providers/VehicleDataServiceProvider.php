<?php

namespace App\Modules\VehicleData\Providers;

use App\Modules\VehicleData\Support\PlateLookupResolver;
use Illuminate\Support\ServiceProvider;

class VehicleDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlateLookupResolver::class);
    }
}
