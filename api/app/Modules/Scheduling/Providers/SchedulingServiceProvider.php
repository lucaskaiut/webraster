<?php

namespace App\Modules\Scheduling\Providers;

use App\Modules\Scheduling\Console\Commands\SyncServiceOrderTravelLegsCommand;
use App\Modules\ServiceOrder\Console\Commands\SendServiceOrderRemindersCommand;
use Illuminate\Support\ServiceProvider;

class SchedulingServiceProvider extends ServiceProvider
{
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
