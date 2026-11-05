<?php

namespace App\Modules\ExternalApi\Providers;

use App\Modules\ExternalApi\Services\ExternalApiRecorder;
use App\Modules\ExternalApi\Support\ExternalApiCatalog;
use App\Modules\ExternalApi\Support\RequestOrigin;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ExternalApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExternalApiCatalog::class);
        $this->app->singleton(RequestOrigin::class);
    }

    public function boot(): void
    {
        Event::listen(ResponseReceived::class, [ExternalApiRecorder::class, 'handleResponse']);
        Event::listen(ConnectionFailed::class, [ExternalApiRecorder::class, 'handleConnectionFailed']);

        // Identifica o job em execução como origem das chamadas feitas por ele.
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $this->app->make(RequestOrigin::class)->startJob($event->job->resolveName());
        });
        Event::listen(JobProcessed::class, fn () => $this->app->make(RequestOrigin::class)->endJob());
        Event::listen(JobExceptionOccurred::class, fn () => $this->app->make(RequestOrigin::class)->endJob());
    }
}
