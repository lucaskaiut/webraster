<?php

namespace App\Modules\Scheduling\Providers;

use App\Modules\Scheduling\Console\Commands\SyncServiceOrderTravelLegsCommand;
use App\Modules\Scheduling\Services\RouteEstimator;
use App\Modules\ServiceOrder\Console\Commands\SendServiceOrderRemindersCommand;
use Illuminate\Support\ServiceProvider;

class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Uma instância por requisição/job: todos os serviços compartilham as
        // rotas já resolvidas em memória, descartadas ao fim do ciclo.
        $this->app->scoped(RouteEstimator::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SendServiceOrderRemindersCommand::class,
                SyncServiceOrderTravelLegsCommand::class,
            ]);
        }
    }
}
