<?php

namespace App\Modules\Client\Providers;

use App\Modules\Client\Support\CurrentClient;
use Illuminate\Support\ServiceProvider;

class ClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentClient::class);
    }
}
