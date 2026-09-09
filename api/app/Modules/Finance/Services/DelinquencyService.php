<?php

namespace App\Modules\Finance\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Enums\ReceivableStatus;
use App\Modules\Finance\Models\FinanceReceivable;
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

        FinanceReceivable::query()
            ->with('contract')
            ->where('status', ReceivableStatus::OVERDUE->value)
            ->whereHas('contract', fn ($q) => $q->where('block_on_overdue', true))
            ->orderBy('id')
            ->chunkById(100, function ($receivables) use ($today, &$suspendedClients): void {
                foreach ($receivables as $receivable) {
                    /** @var FinanceReceivable $receivable */
                    $contract = $receivable->contract;
                    if ($contract === null || ! $contract->block_on_overdue) {
                        continue;
                    }

                    $blockAfter = (int) ($contract->block_after_days ?? 0);
                    $threshold = CarbonImmutable::parse($receivable->due_at)->addDays($blockAfter);

                    if ($threshold->greaterThan($today)) {
                        continue;
                    }

                    $clientId = (int) $receivable->client_id;
                    if (isset($suspendedClients[$clientId])) {
                        continue;
                    }

                    $this->suspendClientEquipments($clientId);
                    $suspendedClients[$clientId] = true;
                }
            });

        return count($suspendedClients);
    }

    public function unsuspendClient(int $clientId): int
    {
        $openOverdue = FinanceReceivable::query()
            ->where('client_id', $clientId)
            ->where('status', ReceivableStatus::OVERDUE->value)
            ->whereHas('contract', fn ($q) => $q->where('block_on_overdue', true))
            ->exists();

        if ($openOverdue) {
            return 0;
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
