<?php

namespace App\Modules\Finance\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DelinquencyService
{
    public function __construct(
        private readonly BillingSuspensionEngine $suspension,
    ) {}

    public function process(): int
    {
        $today = CarbonImmutable::today();
        $suspendedClients = [];

        FinanceBilling::query()
            ->with('subscription')
            ->where('status', BillingStatus::OVERDUE->value)
            ->whereHas('subscription', fn ($q) => $q->where('block_on_overdue', true))
            ->orderBy('id')
            ->chunkById(100, function ($billings) use ($today, &$suspendedClients): void {
                foreach ($billings as $billing) {
                    /** @var FinanceBilling $billing */
                    $subscription = $billing->subscription;
                    if ($subscription === null || ! $subscription->block_on_overdue) {
                        continue;
                    }

                    $blockAfter = (int) ($subscription->block_after_days ?? 0);
                    $threshold = CarbonImmutable::parse($billing->due_at)->addDays($blockAfter);

                    if ($threshold->greaterThan($today)) {
                        continue;
                    }

                    $clientId = (int) $billing->client_id;
                    if (isset($suspendedClients[$clientId])) {
                        continue;
                    }

                    $this->suspendClientEquipments($clientId);
                    $suspendedClients[$clientId] = true;
                }
            });

        return count($suspendedClients);
    }

    public function unsuspendClient(int $clientId, bool $force = false): int
    {
        if (! $force) {
            $openOverdue = FinanceBilling::query()
                ->where('client_id', $clientId)
                ->where('status', BillingStatus::OVERDUE->value)
                ->whereHas('subscription', fn ($q) => $q->where('block_on_overdue', true))
                ->exists();

            if ($openOverdue) {
                return 0;
            }
        }

        $equipments = $this->billingSuspendedEquipmentsForClient($clientId);
        $count = 0;

        foreach ($equipments as $equipment) {
            $this->suspension->unsuspend($equipment);
            $count++;
        }

        return $count;
    }

    private function suspendClientEquipments(int $clientId): void
    {
        foreach ($this->equipmentsForClient($clientId) as $equipment) {
            if ($equipment->isBillingSuspended()) {
                continue;
            }

            $this->suspension->suspend($equipment);
        }
    }

    /**
     * @return Collection<int, Equipment>
     */
    private function equipmentsForClient(int $clientId): Collection
    {
        $vehicleIds = Vehicle::query()
            ->where('client_id', $clientId)
            ->pluck('id');

        if ($vehicleIds->isEmpty()) {
            return collect();
        }

        return Equipment::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->get();
    }

    /**
     * @return Collection<int, Equipment>
     */
    private function billingSuspendedEquipmentsForClient(int $clientId): Collection
    {
        $vehicleIds = Vehicle::query()
            ->where('client_id', $clientId)
            ->pluck('id');

        if ($vehicleIds->isEmpty()) {
            return collect();
        }

        return Equipment::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('billing_suspended_at')
            ->get();
    }
}
