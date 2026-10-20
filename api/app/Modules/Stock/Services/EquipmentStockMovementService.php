<?php

namespace App\Modules\Stock\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Stock\Enums\EquipmentStockMovementType;
use App\Modules\Stock\Models\EquipmentStockMovement;
use App\Modules\Stock\Models\Stock;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Livro de movimentações de estoque dos equipamentos.
 *
 * Cada linha guarda a origem, o destino, a descrição, o instante e o usuário
 * responsável. Os equipamentos são itens únicos (IMEI), portanto não há
 * quantidade/saldo neste livro.
 */
class EquipmentStockMovementService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @return Collection<int, EquipmentStockMovement>
     */
    public function historyForEquipment(Equipment $equipment): Collection
    {
        return EquipmentStockMovement::query()
            ->where('equipment_id', $equipment->getKey())
            ->with(['fromStock', 'toStock', 'movedBy'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    /**
     * Registra entrada, transferência ou saída administrativa de estoque.
     */
    public function recordChange(
        Equipment $equipment,
        ?int $fromStockId,
        ?int $toStockId,
        ?CarbonImmutable $occurredAt = null,
    ): EquipmentStockMovement {
        [$fromStock, $toStock] = $this->stocks($fromStockId, $toStockId);

        $type = match (true) {
            $fromStock === null && $toStock !== null => EquipmentStockMovementType::ENTRY,
            $fromStock !== null && $toStock === null => EquipmentStockMovementType::EXIT,
            default => EquipmentStockMovementType::TRANSFER,
        };

        $description = match ($type) {
            EquipmentStockMovementType::ENTRY => sprintf(
                'Entrada no estoque %s.',
                $toStock?->name ?? 'selecionado',
            ),
            EquipmentStockMovementType::EXIT => sprintf(
                'Removido do estoque %s.',
                $fromStock?->name ?? 'anterior',
            ),
            default => sprintf(
                'Transferido de %s para %s.',
                $fromStock?->name ?? 'sem estoque',
                $toStock?->name ?? 'sem estoque',
            ),
        };

        return $this->record(
            equipment: $equipment,
            fromStock: $fromStock,
            toStock: $toStock,
            type: $type,
            description: $description,
            occurredAt: $occurredAt,
        );
    }

    public function recordInstallation(
        Equipment $equipment,
        Vehicle $vehicle,
        ?int $fromStockId,
        ?string $notes = null,
        ?string $occurredAt = null,
        ?Equipment $replacedEquipment = null,
    ): EquipmentStockMovement {
        $fromStock = $fromStockId !== null ? Stock::query()->find($fromStockId) : null;

        $description = sprintf(
            'Instalado no veículo %s%s.',
            $vehicle->plate,
            filled($vehicle->client?->name) ? ' ('.$vehicle->client->name.')' : '',
        );

        if ($replacedEquipment !== null) {
            $description = sprintf(
                'Instalado no veículo %s%s em troca do equipamento %s.',
                $vehicle->plate,
                filled($vehicle->client?->name) ? ' ('.$vehicle->client->name.')' : '',
                $replacedEquipment->imei ?? $replacedEquipment->uuid,
            );
        }

        return $this->record(
            equipment: $equipment,
            fromStock: $fromStock,
            toStock: null,
            type: EquipmentStockMovementType::INSTALLATION,
            description: $this->withNotes($description, $notes),
            occurredAt: $this->parseOccurredAt($occurredAt),
        );
    }

    public function recordReturn(
        Equipment $equipment,
        Vehicle $vehicle,
        ?Stock $returnStock,
        ?string $notes = null,
        ?string $occurredAt = null,
        bool $swap = false,
    ): EquipmentStockMovement {
        $description = sprintf(
            'Removido do veículo %s%s.',
            $vehicle->plate,
            $swap ? ' para troca de equipamento' : '',
        );

        $description .= $returnStock !== null
            ? sprintf(' Retornado ao estoque %s.', $returnStock->name)
            : ' Sem estoque de retorno definido.';

        return $this->record(
            equipment: $equipment,
            fromStock: null,
            toStock: $returnStock,
            type: EquipmentStockMovementType::RETURN,
            description: $this->withNotes($description, $notes),
            occurredAt: $this->parseOccurredAt($occurredAt),
        );
    }

    /**
     * Estoque de retorno: o último estoque de origem de uma instalação.
     */
    public function returnStockFor(Equipment $equipment): ?Stock
    {
        $movement = EquipmentStockMovement::query()
            ->where('equipment_id', $equipment->getKey())
            ->where('type', EquipmentStockMovementType::INSTALLATION->value)
            ->whereNotNull('from_stock_id')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        return $movement?->fromStock;
    }

    private function record(
        Equipment $equipment,
        ?Stock $fromStock,
        ?Stock $toStock,
        EquipmentStockMovementType $type,
        string $description,
        ?CarbonImmutable $occurredAt = null,
    ): EquipmentStockMovement {
        $occurredAt ??= CarbonImmutable::now();
        $actor = Auth::user();

        $movement = EquipmentStockMovement::query()->create([
            'equipment_id' => $equipment->getKey(),
            'from_stock_id' => $fromStock?->getKey(),
            'to_stock_id' => $toStock?->getKey(),
            'type' => $type->value,
            'description' => $description,
            'moved_by' => $actor?->getKey(),
            'occurred_at' => $occurredAt,
        ]);

        if ($actor !== null) {
            $this->audit->recordEntity(
                $actor,
                AuditAction::EquipmentStockMoved,
                'equipment',
                $equipment->uuid,
                [
                    'movement_id' => $movement->uuid,
                    'type' => $type->value,
                    'from_stock' => $fromStock?->name,
                    'to_stock' => $toStock?->name,
                    'description' => $description,
                ],
            );
        }

        return $movement->load(['fromStock', 'toStock', 'movedBy']);
    }

    /**
     * @return array{0: ?Stock, 1: ?Stock}
     */
    private function stocks(?int $fromStockId, ?int $toStockId): array
    {
        $ids = array_values(array_filter([$fromStockId, $toStockId]));

        if ($ids === []) {
            return [null, null];
        }

        $stocks = Stock::query()->whereIn('id', $ids)->get()->keyBy('id');

        return [
            $fromStockId !== null ? $stocks->get($fromStockId) : null,
            $toStockId !== null ? $stocks->get($toStockId) : null,
        ];
    }

    private function parseOccurredAt(?string $occurredAt): CarbonImmutable
    {
        return $occurredAt !== null && $occurredAt !== ''
            ? CarbonImmutable::parse($occurredAt)
            : CarbonImmutable::now();
    }

    private function withNotes(string $description, ?string $notes): string
    {
        $notes = trim((string) $notes);

        return $notes !== '' ? $description.' '.$notes : $description;
    }
}
