<?php

namespace App\Modules\Alert\Jobs;

use App\Modules\Alert\Services\OfflineAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckOfflineDevicesJob implements ShouldQueue
{
    use Queueable;

    public function handle(OfflineAlertService $service): void
    {
        $alerts = $service->scan();

        Log::debug('alert.offline_scan', [
            'generated' => $alerts->count(),
        ]);
    }
}
