<?php

namespace App\Modules\Scheduling\Console\Commands;

use App\Modules\Scheduling\Services\SchedulingService;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Recalcula os trechos de deslocamento encadeados das OS existentes.
 */
class SyncServiceOrderTravelLegsCommand extends Command
{
    protected $signature = 'service-orders:sync-travel
        {--date= : Data local no formato Y-m-d}
        {--technician= : UUID do técnico}';

    protected $description = 'Recalcula origem/destino dos deslocamentos das OS.';

    public function handle(SchedulingService $scheduling): int
    {
        $query = ServiceOrder::query()
            ->whereNotIn('status', [
                ServiceOrderStatus::CANCELLED->value,
                ServiceOrderStatus::COMPLETED->value,
            ])
            ->whereNotNull('technician_id')
            ->whereNotNull('scheduled_start_at');

        if ($date = $this->option('date')) {
            $day = CarbonImmutable::parse((string) $date, $scheduling->timezone());
            $query->whereBetween('scheduled_start_at', [
                $day->startOfDay()->utc(),
                $day->endOfDay()->utc(),
            ]);
        }

        if ($technicianUuid = $this->option('technician')) {
            $technician = User::query()->where('uuid', (string) $technicianUuid)->first();

            if ($technician === null) {
                $this->error('Técnico não encontrado.');

                return self::FAILURE;
            }

            $query->where('technician_id', $technician->getKey());
        }

        $groups = $query
            ->get(['id', 'technician_id', 'scheduled_start_at'])
            ->groupBy(fn (ServiceOrder $order) => sprintf(
                '%d|%s',
                $order->technician_id,
                $order->scheduled_start_at->setTimezone($scheduling->timezone())->format('Y-m-d'),
            ));

        foreach ($groups as $orders) {
            $first = $orders->first();

            if ($first === null) {
                continue;
            }

            $scheduling->syncDayLegs(
                (int) $first->technician_id,
                CarbonImmutable::parse($first->scheduled_start_at),
            );
        }

        $this->info(sprintf('%d dia(s) de agenda recalculado(s).', $groups->count()));

        return self::SUCCESS;
    }
}
