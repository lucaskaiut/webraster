<?php

namespace App\Modules\Chat\Providers;

use App\Modules\Chat\Gateways\EvolutionGateway;
use App\Modules\Chat\Support\MessagingGatewayResolver;
use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MessagingGatewayResolver::class);
        $this->app->singleton(EvolutionGateway::class);
    }
}
