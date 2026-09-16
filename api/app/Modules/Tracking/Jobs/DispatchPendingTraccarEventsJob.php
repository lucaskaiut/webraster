<?php

namespace App\Modules\Tracking\Jobs;

use App\Modules\Tracking\Enums\TraccarEventStatus;
use App\Modules\Tracking\Models\TraccarEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recupera eventos que ficaram pendentes (falha entre o insert e o dispatch,
 * ou worker indisponível no momento da ingestão).
 */
class DispatchPendingTraccarEventsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $staleBefore = now()->subMinutes(5);

        TraccarEvent::query()
            ->where('status', TraccarEventStatus::PENDING->value)
            ->where(function ($query) use ($staleBefore): void {
                $query->whereNull('dispatched_at')
                    ->orWhere('dispatched_at', '<=', $staleBefore);
            })
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->each(fn (TraccarEvent $event) => ProcessTraccarEvent::dispatch($event->getKey()));
    }
}
