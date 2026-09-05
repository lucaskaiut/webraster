<?php

namespace App\Modules\Vehicle\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Vehicle\Enums\AssignmentEventType;
use App\Modules\Vehicle\Models\EquipmentAssignmentEvent;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentAssignmentService
{
    public function install(Vehicle $vehicle, Equipment $equipment, ?string $notes = null, ?string $occurredAt = null): EquipmentAssignmentEvent
    {
        $this->assertSameTenant($vehicle, $equipment);
        $this->assertEquipmentAvailable($equipment);
        $this->assertVehicleHasNoEquipment($vehicle);

        return DB::transaction(function () use ($vehicle, $equipment, $notes, $occurredAt): EquipmentAssignmentEvent {
            $equipment->vehicle_id = $vehicle->getKey();
            $equipment->save();

            return $this->record(
                vehicle: $vehicle,
                equipment: $equipment,
                event: AssignmentEventType::INSTALLATION,
                notes: $notes,
                occurredAt: $occurredAt,
            );
        });
    }

    public function remove(Vehicle $vehicle, ?string $notes = null, ?string $occurredAt = null): EquipmentAssignmentEvent
    {
        $equipment = $vehicle->equipment;

        if ($equipment === null) {
            throw ValidationException::withMessages([
                'equipment_id' => ['Este veículo não possui equipamento instalado.'],
            ]);
        }

        return DB::transaction(function () use ($vehicle, $equipment, $notes, $occurredAt): EquipmentAssignmentEvent {
            $equipment->vehicle_id = null;
            $equipment->save();

            return $this->record(
                vehicle: $vehicle,
                equipment: $equipment,
                event: AssignmentEventType::REMOVAL,
                notes: $notes,
                occurredAt: $occurredAt,
            );
        });
    }

    public function swap(
        Vehicle $vehicle,
        Equipment $newEquipment,
        ?string $notes = null,
        ?string $occurredAt = null,
    ): EquipmentAssignmentEvent {
        $this->assertSameTenant($vehicle, $newEquipment);
        $this->assertEquipmentAvailable($newEquipment);

        $current = $vehicle->equipment;

        if ($current === null) {
            throw ValidationException::withMessages([
                'equipment_id' => ['Este veículo não possui equipamento instalado para troca.'],
            ]);
        }

        if ((int) $current->getKey() === (int) $newEquipment->getKey()) {
            throw ValidationException::withMessages([
                'equipment_id' => ['Selecione um equipamento diferente do atual.'],
            ]);
        }

        return DB::transaction(function () use ($vehicle, $current, $newEquipment, $notes, $occurredAt): EquipmentAssignmentEvent {
            $current->vehicle_id = null;
            $current->save();

            $newEquipment->vehicle_id = $vehicle->getKey();
            $newEquipment->save();

            return $this->record(
                vehicle: $vehicle,
                equipment: $newEquipment,
                event: AssignmentEventType::SWAP,
                notes: $notes,
                occurredAt: $occurredAt,
                previousEquipment: $current,
            );
        });
    }

    /**
     * @return Collection<int, EquipmentAssignmentEvent>
     */
    public function historyForVehicle(Vehicle $vehicle): Collection
    {
        return EquipmentAssignmentEvent::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->with(['equipment', 'previousEquipment'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    /**
     * @return Collection<int, EquipmentAssignmentEvent>
     */
    public function historyForEquipment(Equipment $equipment): Collection
    {
        return EquipmentAssignmentEvent::query()
            ->where(function ($query) use ($equipment): void {
                $query->where('equipment_id', $equipment->getKey())
                    ->orWhere('previous_equipment_id', $equipment->getKey());
            })
            ->with(['vehicle', 'equipment', 'previousEquipment'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    private function record(
        Vehicle $vehicle,
        Equipment $equipment,
        AssignmentEventType $event,
        ?string $notes,
        ?string $occurredAt,
        ?Equipment $previousEquipment = null,
    ): EquipmentAssignmentEvent {
        return EquipmentAssignmentEvent::query()->create([
            'vehicle_id' => $vehicle->getKey(),
            'equipment_id' => $equipment->getKey(),
            'previous_equipment_id' => $previousEquipment?->getKey(),
            'event' => $event,
            'occurred_at' => $occurredAt
                ? CarbonImmutable::parse($occurredAt)
                : CarbonImmutable::now(),
            'notes' => $notes,
        ])->load(['equipment', 'previousEquipment', 'vehicle']);
    }

    private function assertSameTenant(Vehicle $vehicle, Equipment $equipment): void
    {
        if ((int) $vehicle->tenant_id !== (int) $equipment->tenant_id) {
            throw ValidationException::withMessages([
                'equipment_id' => ['O equipamento não pertence a esta organização.'],
            ]);
        }
    }

    private function assertEquipmentAvailable(Equipment $equipment): void
    {
        if ($equipment->isAssigned()) {
            throw ValidationException::withMessages([
                'equipment_id' => ['Este equipamento já está instalado em outro veículo.'],
            ]);
        }
    }

    private function assertVehicleHasNoEquipment(Vehicle $vehicle): void
    {
        if ($vehicle->equipment()->exists()) {
            throw ValidationException::withMessages([
                'equipment_id' => ['Este veículo já possui um equipamento instalado. Use a troca.'],
            ]);
        }
    }
}
