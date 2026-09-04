<?php

namespace App\Modules\Webhook\Providers;

use App\Modules\Webhook\Services\WebhookEventRegistry;
use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WebhookEventRegistry::class, function () {
            return new WebhookEventRegistry();
        });
    }

    public function boot(): void
    {
        $registry = $this->app->make(WebhookEventRegistry::class);

        $registry->register([
            'user.created' => 'Usuário criado',
            'user.updated' => 'Usuário atualizado',
            'user.deleted' => 'Usuário removido',
            'tenant.created' => 'Tenant criado',
            'tenant.updated' => 'Tenant atualizado',
            'tenant.deleted' => 'Tenant removido',
        ]);
    }
}
