<?php

namespace App\Modules\Tracking\Jobs;

use App\Modules\Tracking\Models\TraccarEvent;
use App\Modules\Tracking\Services\TraccarEventProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessTraccarEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue((string) config('traccar.queue', 'tracking'));
    }

    public function uniqueId(): string
    {
        return 'traccar-event-'.$this->eventId;
    }

    public function handle(TraccarEventProcessor $processor): void
    {
        $event = TraccarEvent::query()->find($this->eventId);

        if ($event === null) {
            Log::warning('traccar.event_missing', [
                'event_id' => $this->eventId,
            ]);

            return;
        }

        $event->forceFill(['attempts' => $event->attempts + 1])->save();

        $processor->process($event);
    }

    public function failed(?Throwable $exception): void
    {
        $event = TraccarEvent::query()->find($this->eventId);

        if ($event === null || $event->status->isTerminal()) {
            return;
        }

        $event->markFailed($exception?->getMessage() ?? 'unknown_error');
    }
}
