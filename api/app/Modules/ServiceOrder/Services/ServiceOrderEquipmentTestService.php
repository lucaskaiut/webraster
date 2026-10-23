<?php

namespace App\Modules\ServiceOrder\Services;

use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Services\DeviceCommandService;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\ServiceOrder\Enums\ServiceOrderHistoryAction;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderEquipmentTest;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\User\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Executa os testes do equipamento selecionado na OS: bloqueio, desbloqueio,
 * ignição e localização. Os comandos enviados ao rastreador continuam
 * auditados em device_command_logs; apenas os testes bem-sucedidos ficam
 * registrados na OS.
 */
class ServiceOrderEquipmentTestService
{
    public const ACTION_BLOCK = 'block';

    public const ACTION_UNBLOCK = 'unblock';

    public const ACTION_IGNITION = 'ignition';

    public const ACTION_LOCATION = 'location';

    private const COMMAND_TYPES = [
        self::ACTION_BLOCK => 'engineStop',
        self::ACTION_UNBLOCK => 'engineResume',
    ];

    private const ACTION_LABELS = [
        self::ACTION_BLOCK => 'Bloqueio',
        self::ACTION_UNBLOCK => 'Desbloqueio',
        self::ACTION_IGNITION => 'Ignição',
        self::ACTION_LOCATION => 'Localização',
    ];

    public function __construct(
        private readonly DeviceCommandService $commands,
        private readonly ServiceOrderHistoryService $history,
    ) {}

    public function run(ServiceOrder $order, string $action, User $actor): ?ServiceOrderEquipmentTest
    {
        if (! isset(self::ACTION_LABELS[$action])) {
            throw ValidationException::withMessages([
                'action' => ['Teste de equipamento inválido.'],
            ]);
        }

        $equipment = $this->equipmentFor($order);

        return match ($action) {
            self::ACTION_IGNITION => $this->runIgnition($order, $equipment, $actor),
            self::ACTION_LOCATION => $this->runLocation($order, $equipment, $actor),
            default => $this->runCommand($order, $equipment, $action, $actor),
        };
    }

    /**
     * Última posição conhecida do equipamento selecionado na OS, usada para
     * exibir o mapa antes da confirmação do teste de localização.
     */
    public function latestLocation(ServiceOrder $order): ?GpsPosition
    {
        return $this->latestPosition($this->equipmentFor($order));
    }

    private function runCommand(
        ServiceOrder $order,
        Equipment $equipment,
        string $action,
        User $actor,
    ): ?ServiceOrderEquipmentTest {
        $commandType = self::COMMAND_TYPES[$action];

        $log = $this->commands->send(
            $equipment,
            $commandType,
            [],
            $actor,
            $order,
            throwOnFailure: false,
        );

        // O log do comando é mantido para auditoria, mas o teste só entra na
        // OS quando o rastreador confirma a execução.
        if ($log->status !== DeviceCommandStatus::SUCCESS) {
            return null;
        }

        $test = $this->record(
            $order,
            $equipment,
            $actor,
            $action,
            $commandType,
            DeviceCommandStatus::SUCCESS,
            $log->response,
            $log->requested_at,
            $log->executed_at,
            $log->getKey(),
        );

        $this->history->record(
            $order,
            ServiceOrderHistoryAction::EQUIPMENT_TESTED,
            $actor,
            'equipment_test',
            null,
            self::ACTION_LABELS[$action],
            [
                'action' => $action,
                'command_type' => $commandType,
                'status' => $test->status?->value,
                'equipment_id' => $equipment->uuid,
                'command_log_id' => $log->uuid,
                'test_id' => $test->uuid,
            ],
        );

        return $test;
    }

    /**
     * Lê a última posição conhecida do equipamento e registra o estado da
     * ignição no momento do teste (ligada/desligada).
     */
    private function runIgnition(
        ServiceOrder $order,
        Equipment $equipment,
        User $actor,
    ): ?ServiceOrderEquipmentTest {
        $position = $this->latestPosition($equipment, requireIgnition: true);

        $ignition = $position?->ignition;

        if ($ignition === null) {
            return null;
        }

        $test = $this->record(
            $order,
            $equipment,
            $actor,
            self::ACTION_IGNITION,
            null,
            DeviceCommandStatus::SUCCESS,
            [
                'ignition' => $ignition,
                'recorded_at' => $position?->recorded_at?->toIso8601String(),
            ],
            now(),
            now(),
        );

        $this->history->record(
            $order,
            ServiceOrderHistoryAction::EQUIPMENT_TESTED,
            $actor,
            'equipment_test',
            null,
            $this->ignitionLabel($ignition),
            [
                'action' => self::ACTION_IGNITION,
                'ignition' => $ignition,
                'position_recorded_at' => $position?->recorded_at?->toIso8601String(),
                'equipment_id' => $equipment->uuid,
                'test_id' => $test->uuid,
            ],
        );

        return $test;
    }

    /**
     * Registra a localização atual do equipamento no momento em que o técnico
     * confirma o mapa. A coordenada vem do rastreador, nunca do celular.
     */
    private function runLocation(
        ServiceOrder $order,
        Equipment $equipment,
        User $actor,
    ): ?ServiceOrderEquipmentTest {
        $position = $this->latestPosition($equipment);

        if ($position === null) {
            return null;
        }

        $test = $this->record(
            $order,
            $equipment,
            $actor,
            self::ACTION_LOCATION,
            null,
            DeviceCommandStatus::SUCCESS,
            [
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'recorded_at' => $position->recorded_at?->toIso8601String(),
            ],
            now(),
            now(),
        );

        $this->history->record(
            $order,
            ServiceOrderHistoryAction::EQUIPMENT_TESTED,
            $actor,
            'equipment_test',
            null,
            'Localização confirmada',
            [
                'action' => self::ACTION_LOCATION,
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'position_recorded_at' => $position->recorded_at?->toIso8601String(),
                'equipment_id' => $equipment->uuid,
                'test_id' => $test->uuid,
            ],
        );

        return $test;
    }

    private function equipmentFor(ServiceOrder $order): Equipment
    {
        $order->loadMissing('equipment');
        $equipment = $order->equipment;

        if ($equipment === null) {
            throw ValidationException::withMessages([
                'equipment_id' => ['Selecione o equipamento antes de realizar os testes.'],
            ]);
        }

        return $equipment;
    }

    private function latestPosition(Equipment $equipment, bool $requireIgnition = false): ?GpsPosition
    {
        return GpsPosition::query()
            ->where('equipment_id', $equipment->getKey())
            ->when($requireIgnition, fn ($query) => $query->whereNotNull('ignition'))
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function record(
        ServiceOrder $order,
        Equipment $equipment,
        User $actor,
        string $action,
        ?string $commandType,
        DeviceCommandStatus $status,
        ?array $result,
        mixed $requestedAt,
        mixed $executedAt,
        ?int $commandLogId = null,
    ): ServiceOrderEquipmentTest {
        $test = new ServiceOrderEquipmentTest;
        $test->forceFill([
            'tenant_id' => $order->tenant_id,
            'service_order_id' => $order->getKey(),
            'equipment_id' => $equipment->getKey(),
            'user_id' => $actor->getKey(),
            'device_command_log_id' => $commandLogId,
            'action' => $action,
            'command_type' => $commandType,
            'status' => $status,
            'result' => $result,
            'requested_at' => $requestedAt,
            'executed_at' => $executedAt,
        ])->save();

        return $test->load(['user', 'equipment']);
    }

    private function ignitionLabel(bool $ignition): string
    {
        return $ignition ? 'Ignição ligada' : 'Ignição desligada';
    }
}
