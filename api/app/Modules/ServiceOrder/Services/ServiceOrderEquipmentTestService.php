<?php

namespace App\Modules\ServiceOrder\Services;

use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\DeviceCommand\Services\DeviceCommandService;
use App\Modules\ServiceOrder\Enums\ServiceOrderHistoryAction;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Executa os testes de bloqueio/desbloqueio do equipamento selecionado na OS.
 * O log do comando é vinculado à OS e também gera um evento no histórico.
 */
class ServiceOrderEquipmentTestService
{
    public const ACTION_BLOCK = 'block';

    public const ACTION_UNBLOCK = 'unblock';

    private const COMMAND_TYPES = [
        self::ACTION_BLOCK => 'engineStop',
        self::ACTION_UNBLOCK => 'engineResume',
    ];

    public function __construct(
        private readonly DeviceCommandService $commands,
        private readonly ServiceOrderHistoryService $history,
    ) {}

    public function run(ServiceOrder $order, string $action, User $actor): DeviceCommandLog
    {
        $commandType = self::COMMAND_TYPES[$action] ?? null;

        if ($commandType === null) {
            throw ValidationException::withMessages([
                'action' => ['Teste de equipamento inválido.'],
            ]);
        }

        $order->loadMissing('equipment');
        $equipment = $order->equipment;

        if ($equipment === null) {
            throw ValidationException::withMessages([
                'equipment_id' => ['Selecione o equipamento antes de realizar os testes.'],
            ]);
        }

        $log = $this->commands->send(
            $equipment,
            $commandType,
            [],
            $actor,
            $order,
            throwOnFailure: false,
        );

        $this->history->record(
            $order,
            ServiceOrderHistoryAction::EQUIPMENT_TESTED,
            $actor,
            'equipment_test',
            null,
            $action === self::ACTION_BLOCK ? 'Bloqueio' : 'Desbloqueio',
            [
                'action' => $action,
                'command_type' => $commandType,
                'status' => $log->status?->value,
                'equipment_id' => $equipment->uuid,
                'command_log_id' => $log->uuid,
            ],
        );

        return $log;
    }
}
