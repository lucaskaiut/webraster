<?php

namespace App\Modules\ServiceOrder\Services;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Checklist\Services\ChecklistService;
use App\Modules\Client\Models\Client;
use App\Modules\Commission\Services\CommissionService;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Services\FinanceBillingService;
use App\Modules\Scheduling\Services\SchedulingService;
use App\Modules\Scheduling\Support\Coordinates;
use App\Modules\Scheduling\Support\TimeGrid;
use App\Modules\Service\Models\Service;
use App\Modules\ServiceOrder\Enums\ServiceOrderHistoryAction;
use App\Modules\ServiceOrder\Enums\ServiceOrderPriority;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderItem;
use App\Modules\ServiceOrder\Models\ServiceOrderPhoto;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Services\EquipmentAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ServiceOrderService
{
    public function __construct(
        private readonly ServiceOrderHistoryService $history,
        private readonly ServiceOrderNotificationService $notifications,
        private readonly FinanceBillingService $billings,
        private readonly SchedulingService $scheduling,
        private readonly EquipmentAssignmentService $assignments,
        private readonly CommissionService $commissions,
        private readonly ChecklistService $checklists,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['client', 'vehicle', 'equipment', 'technician', 'creator'])
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<string, Collection<int, ServiceOrder>>
     */
    public function kanban(array $filters = []): Collection
    {
        $orders = $this->filteredQuery($filters)
            ->with(['client', 'vehicle', 'technician'])
            ->whereIn('status', [
                ServiceOrderStatus::OPEN->value,
                ServiceOrderStatus::IN_PROGRESS->value,
                ServiceOrderStatus::COMPLETED->value,
                ServiceOrderStatus::CANCELLED->value,
            ])
            ->orderByDesc('scheduled_start_at')
            ->orderByDesc('created_at')
            ->limit(400)
            ->get();

        $grouped = collect([
            ServiceOrderStatus::OPEN->value => collect(),
            ServiceOrderStatus::IN_PROGRESS->value => collect(),
            ServiceOrderStatus::COMPLETED->value => collect(),
            ServiceOrderStatus::CANCELLED->value => collect(),
        ]);

        foreach ($orders as $order) {
            $key = $order->status->value;
            if ($grouped->has($key)) {
                $grouped[$key]->push($order);
            }
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ServiceOrder>
     */
    public function calendar(array $filters = []): Collection
    {
        return $this->filteredQuery($filters)
            ->with(['client', 'vehicle', 'technician'])
            ->whereNotNull('scheduled_start_at')
            ->whereNotIn('status', [ServiceOrderStatus::CANCELLED->value])
            ->orderBy('scheduled_start_at')
            ->limit(500)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): ServiceOrder
    {
        $payload = $this->payload($data);
        $this->assertRelations($payload);
        $payload = array_merge($payload, $this->deriveScheduling($payload, $data));
        $this->assertScheduleConflict($payload, ignore: (bool) ($data['ignore_schedule_conflict'] ?? false));

        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        $order = DB::transaction(function () use ($payload, $items, $data, $actor) {
            $payload['number'] = $this->nextNumber();
            $payload['status'] = isset($data['status'])
                ? ServiceOrderStatus::from((string) $data['status'])
                : ServiceOrderStatus::OPEN;
            $payload['created_by'] = $actor->getKey();

            $order = ServiceOrder::query()->create($payload);

            if ($items !== []) {
                $this->syncItems($order, $items);
            }

            $this->history->record($order, ServiceOrderHistoryAction::CREATED, $actor);
            $this->commissions->syncForOrder($order);
            $this->checklists->ensureInstancesForOrder($order);

            if ($order->status === ServiceOrderStatus::IN_PROGRESS) {
                // Não é possível nascer "em andamento" pulando o checklist inicial.
                if ($this->checklists->hasActiveInitialTemplates()) {
                    throw ValidationException::withMessages([
                        'checklists' => ['Preencha o checklist inicial antes de iniciar o atendimento.'],
                    ]);
                }

                $this->checklists->assertReadyToStart($order);
            }

            return $order;
        });

        $this->syncTravelLegsFor($order);
        $order->refresh();

        $order->load(['client', 'vehicle', 'equipment', 'technician', 'creator', 'items.service', 'photos', 'attachments', 'billings', 'commission', 'equipmentTests.user', 'equipmentTests.equipment', 'loanTermCollectedBy']);

        if ($order->technician_id) {
            $this->notifications->notifyAssigned($order);
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ServiceOrder $order, array $data, User $actor): ServiceOrder
    {
        if ($order->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => ['Ordens concluídas ou canceladas não podem ser editadas.'],
            ]);
        }

        $payload = $this->payload($data, $order);
        $this->assertRelations($payload, $order);
        $derived = $this->deriveScheduling($payload, $data, $order);
        $this->assertScheduleConflict(array_merge($payload, $derived), $order, (bool) ($data['ignore_schedule_conflict'] ?? false));

        $previousTechnicianId = $order->technician_id ? (int) $order->technician_id : null;
        $previousStart = $order->scheduled_start_at?->toIso8601String();
        $previousAddress = $order->address;
        $previousInstallationPhoto = $order->installation_location_photo_path;

        $nextStatus = isset($data['status'])
            ? ServiceOrderStatus::from((string) $data['status'])
            : null;

        if (
            $nextStatus !== null
            && $nextStatus !== $order->status
            && ! $actor->hasPermission(Permission::SERVICE_ORDER_CHANGE_STATUS)
        ) {
            throw new AuthorizationException('Você não tem permissão para alterar a situação da OS.');
        }

        $changes = [];
        foreach ($payload as $field => $value) {
            $current = $order->getAttribute($field);
            if ($current instanceof \BackedEnum) {
                $current = $current->value;
            }
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
            if ($current instanceof \DateTimeInterface) {
                $current = CarbonImmutable::parse($current)->toIso8601String();
            }
            if ($value instanceof \DateTimeInterface) {
                $value = CarbonImmutable::parse($value)->toIso8601String();
            }

            if ((string) $current !== (string) $value) {
                $changes[$field] = ['old' => $order->getAttribute($field), 'new' => $payload[$field]];
            }
        }

        $order->fill(array_merge($payload, $derived));
        $order->save();

        $this->deleteReplacedInstallationPhoto($previousInstallationPhoto, $order->installation_location_photo_path);

        foreach ($changes as $field => $change) {
            $action = match ($field) {
                'technician_id' => ServiceOrderHistoryAction::TECHNICIAN_CHANGED,
                'scheduled_start_at', 'scheduled_end_at' => ServiceOrderHistoryAction::SCHEDULE_CHANGED,
                default => ServiceOrderHistoryAction::UPDATED,
            };

            $this->history->record(
                $order,
                $action,
                $actor,
                $field,
                $change['old'],
                $change['new'],
            );
        }

        if (array_key_exists('items', $data)) {
            $before = $order->items()->get()
                ->map(fn (ServiceOrderItem $item) => $this->summarizeItem($item))
                ->all();

            $this->syncItems($order, is_array($data['items']) ? $data['items'] : []);

            $after = $order->items()->get()
                ->map(fn (ServiceOrderItem $item) => $this->summarizeItem($item))
                ->all();

            if ($before !== $after) {
                $this->history->record(
                    $order,
                    ServiceOrderHistoryAction::SERVICES_CHANGED,
                    $actor,
                    'items',
                    $before,
                    $after,
                );
            }
        }

        $statusChanged = $nextStatus !== null && $nextStatus !== $order->status;

        if ($statusChanged) {
            $this->applyStatusChange(
                $order,
                $nextStatus,
                $actor,
                $data['cancellation_reason'] ?? null,
                $data['execution_notes'] ?? null,
            );
        }

        $this->syncTravelLegsForReference($previousTechnicianId, $previousStart);
        $this->syncTravelLegsFor($order);
        $order->refresh();

        // Mudanças de itens ou técnico também precisam refletir no snapshot.
        if (! $statusChanged) {
            $this->commissions->syncForOrder($order);
        }

        $this->notifyScheduleChanges($order->refresh(), $previousTechnicianId, $previousStart, $previousAddress);

        return $order->load(['client', 'vehicle', 'equipment', 'technician', 'creator', 'items.service', 'photos', 'attachments', 'billings', 'commission', 'equipmentTests.user', 'equipmentTests.equipment', 'loanTermCollectedBy']);
    }

    /**
     * Técnico seleciona o equipamento que será instalado durante o atendimento.
     */
    public function assignEquipment(ServiceOrder $order, Equipment $equipment, User $actor): ServiceOrder
    {
        if ($order->status !== ServiceOrderStatus::IN_PROGRESS) {
            throw ValidationException::withMessages([
                'equipment_id' => ['O equipamento só pode ser selecionado com a OS em andamento.'],
            ]);
        }

        if (! $equipment->is_active || $equipment->vehicle_id !== null) {
            throw ValidationException::withMessages([
                'equipment_id' => ['Este equipamento não está disponível para instalação.'],
            ]);
        }

        if (
            $actor->stock_id !== null
            && (int) $equipment->stock_id !== (int) $actor->stock_id
        ) {
            throw ValidationException::withMessages([
                'equipment_id' => ['O equipamento não pertence ao seu estoque.'],
            ]);
        }

        if ((int) $order->equipment_id === (int) $equipment->getKey()) {
            return $order;
        }

        DB::transaction(function () use ($order, $equipment, $actor): void {
            $previous = $order->equipment_id;

            $order->equipment_id = $equipment->getKey();
            $order->save();

            $this->history->record(
                $order,
                ServiceOrderHistoryAction::UPDATED,
                $actor,
                'equipment_id',
                $previous,
                $equipment->getKey(),
            );
        });

        return $order->refresh();
    }

    public function changeStatus(
        ServiceOrder $order,
        ServiceOrderStatus $next,
        User $actor,
        ?string $cancellationReason = null,
        ?string $executionNotes = null,
    ): ServiceOrder {
        $this->applyStatusChange($order, $next, $actor, $cancellationReason, $executionNotes);

        $this->syncTravelLegsFor($order);
        $order->refresh();

        return $order->load(['client', 'vehicle', 'equipment', 'technician', 'creator', 'completer', 'canceller', 'items.service', 'photos', 'attachments', 'billings', 'commission', 'equipmentTests.user', 'equipmentTests.equipment', 'loanTermCollectedBy']);
    }

    private function applyStatusChange(
        ServiceOrder $order,
        ServiceOrderStatus $next,
        User $actor,
        ?string $cancellationReason = null,
        ?string $executionNotes = null,
    ): void {
        DB::transaction(function () use ($order, $next, $actor, $cancellationReason, $executionNotes): void {
            if (! $order->status->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => [sprintf(
                        'Transição inválida: %s → %s.',
                        $order->status->label(),
                        $next->label(),
                    )],
                ]);
            }

            if ($next === ServiceOrderStatus::CANCELLED && blank($cancellationReason)) {
                throw ValidationException::withMessages([
                    'cancellation_reason' => ['Informe o motivo do cancelamento.'],
                ]);
            }

            if ($next === ServiceOrderStatus::IN_PROGRESS) {
                $this->checklists->assertReadyToStart($order);
            }

            if ($next === ServiceOrderStatus::COMPLETED) {
                $this->checklists->assertReadyToComplete($order);
                $this->assertClientSignature($order);
            }

            // A conclusão é o ponto em que o equipamento realmente sai do
            // estoque e entra no veículo selecionado na OS, se ambos existirem.
            if ($next === ServiceOrderStatus::COMPLETED) {
                $this->installEquipmentOnCompletion($order);
            }

            $previous = $order->status;
            $order->status = $next;

            if ($next === ServiceOrderStatus::COMPLETED) {
                $order->completed_at = now();
                $order->completed_by = $actor->getKey();
                if ($executionNotes !== null) {
                    $order->execution_notes = $executionNotes;
                }
            }

            if ($next === ServiceOrderStatus::CANCELLED) {
                $order->cancelled_at = now();
                $order->cancelled_by = $actor->getKey();
                $order->cancellation_reason = $cancellationReason;
            }

            $order->save();

            $action = match ($next) {
                ServiceOrderStatus::COMPLETED => ServiceOrderHistoryAction::COMPLETED,
                ServiceOrderStatus::CANCELLED => ServiceOrderHistoryAction::CANCELLED,
                default => ServiceOrderHistoryAction::STATUS_CHANGED,
            };

            $this->history->record(
                $order,
                $action,
                $actor,
                'status',
                $previous,
                $next,
                $next === ServiceOrderStatus::CANCELLED
                    ? ['cancellation_reason' => $cancellationReason]
                    : null,
            );

            $this->commissions->syncForOrder($order);

            $this->notifications->notifyStatusChanged(
                $order,
                $previous->label(),
                $next->label(),
            );
        });
    }

    /**
     * A OS só pode ser concluída com a assinatura do cliente registrada.
     * O termo de comodato é opcional e depende da configuração da empresa.
     */
    private function assertClientSignature(ServiceOrder $order): void
    {
        if (! $order->hasClientSignature()) {
            throw ValidationException::withMessages([
                'loan_term_acceptance' => [
                    'É necessário coletar a assinatura do cliente antes de concluir a OS.',
                ],
            ]);
        }
    }

    /**
     * Instala no veículo o equipamento selecionado na OS ao concluí-la.
     *
     * Se o veículo já tiver o mesmo equipamento, nada é feito. Se tiver outro,
     * a troca é registrada pelo fluxo de instalação/troca existente, que devolve
     * o equipamento anterior ao estoque e audita as duas movimentações.
     */
    private function installEquipmentOnCompletion(ServiceOrder $order): void
    {
        $order->load(['equipment', 'vehicle']);
        $equipment = $order->equipment;
        $vehicle = $order->vehicle;

        if ($equipment === null || $vehicle === null) {
            return;
        }

        if ($equipment->vehicle_id !== null && (int) $equipment->vehicle_id === (int) $vehicle->getKey()) {
            return;
        }

        $vehicle->load('equipment');
        $notes = sprintf('Concluída pela OS %s.', $order->code);

        if (
            $vehicle->equipment !== null
            && (int) $vehicle->equipment->getKey() === (int) $equipment->getKey()
        ) {
            return;
        }

        if ($vehicle->equipment !== null) {
            $this->assignments->swap($vehicle, $equipment, $notes);

            return;
        }

        $this->assignments->install($vehicle, $equipment, $notes);
    }

    /**
     * Gera a cobrança do cliente a partir dos serviços realizados na OS.
     */
    public function generateBilling(ServiceOrder $order, User $actor, ?string $dueAt = null): FinanceBilling
    {
        $order->loadMissing('items');

        if ($order->status === ServiceOrderStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível gerar cobrança para uma OS cancelada.'],
            ]);
        }

        if ($order->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Adicione ao menos um serviço realizado antes de gerar a cobrança.'],
            ]);
        }

        $hasOpenBilling = $order->billings()
            ->whereNotIn('status', [
                BillingStatus::CANCELLED->value,
                BillingStatus::REFUNDED->value,
            ])
            ->exists();

        if ($hasOpenBilling) {
            throw ValidationException::withMessages([
                'billing' => ['Esta OS já possui uma cobrança em aberto.'],
            ]);
        }

        $billing = $this->billings->generateForServiceOrder(
            $order,
            $dueAt !== null ? CarbonImmutable::parse($dueAt) : null,
        );

        $this->history->record(
            $order,
            ServiceOrderHistoryAction::BILLING_GENERATED,
            $actor,
            'billing',
            null,
            $billing->code,
            ['billing_uuid' => $billing->uuid],
        );

        return $billing;
    }

    public function addPhoto(ServiceOrder $order, string $path): ServiceOrderPhoto
    {
        $this->assertPhotosMutable($order);

        return $order->photos()->create([
            'path' => $path,
            'sort_order' => ((int) $order->photos()->max('sort_order')) + 1,
        ]);
    }

    public function removePhoto(ServiceOrder $order, ServiceOrderPhoto $photo): void
    {
        $this->assertPhotosMutable($order);

        if ((int) $photo->service_order_id !== (int) $order->getKey()) {
            throw (new ModelNotFoundException)->setModel(ServiceOrderPhoto::class, [$photo->getKey()]);
        }

        Storage::disk('public')->delete($photo->path);
        $photo->delete();
    }

    private function assertPhotosMutable(ServiceOrder $order): void
    {
        if ($order->status === ServiceOrderStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível alterar as fotos de uma OS cancelada.'],
            ]);
        }
    }

    /**
     * Substitui os itens da OS pelos enviados, mantendo o snapshot do
     * nome e do valor do serviço no momento do lançamento.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(ServiceOrder $order, array $items): void
    {
        $order->items()->delete();

        $serviceIds = collect($items)->pluck('service_id')->filter()->unique()->values();

        $services = $serviceIds->isEmpty()
            ? collect()
            : Service::query()->whereIn('id', $serviceIds)->get()->keyBy('id');

        foreach (array_values($items) as $item) {
            $service = $services->get((int) ($item['service_id'] ?? 0));

            if ($service === null) {
                throw ValidationException::withMessages([
                    'items' => ['Serviço inválido na lista de serviços realizados.'],
                ]);
            }

            $order->items()->create([
                'service_id' => $service->getKey(),
                'name' => $service->name,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'unit_amount_cents' => max(0, (int) ($item['unit_amount_cents'] ?? 0)),
                'commission_type' => $service->commission_type,
                'commission_value' => $service->commission_value,
            ]);
        }

        $order->unsetRelation('items');
    }

    private function syncTravelLegsFor(ServiceOrder $order): void
    {
        if (! $order->technician_id || ! $order->scheduled_start_at) {
            return;
        }

        $this->scheduling->syncDayLegs(
            (int) $order->technician_id,
            CarbonImmutable::parse($order->scheduled_start_at),
        );
    }

    private function syncTravelLegsForReference(?int $technicianId, ?string $start): void
    {
        if (! $technicianId || ! $start) {
            return;
        }

        $this->scheduling->syncDayLegs($technicianId, CarbonImmutable::parse($start));
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeItem(ServiceOrderItem $item): array
    {
        return [
            'service_id' => $item->service_id,
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit_amount_cents' => $item->unit_amount_cents,
        ];
    }

    public function delete(ServiceOrder $order): void
    {
        if ($order->status === ServiceOrderStatus::IN_PROGRESS) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível excluir uma OS em andamento. Cancele-a primeiro.'],
            ]);
        }

        $technicianId = $order->technician_id ? (int) $order->technician_id : null;
        $start = $order->scheduled_start_at?->toIso8601String();

        $order->delete();

        $this->syncTravelLegsForReference($technicianId, $start);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters)
    {
        $query = ServiceOrder::query();

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('installation_location', 'like', "%{$search}%");

                if (preg_match('/^OS-?0*(\d+)$/i', $search, $matches)) {
                    $builder->orWhere('number', (int) $matches[1]);
                } elseif (ctype_digit($search)) {
                    $builder->orWhere('number', (int) $search);
                }
            });
        }

        foreach (['status', 'priority'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        foreach (['client_id', 'vehicle_id', 'technician_id', 'equipment_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['from'])) {
            $query->where('scheduled_start_at', '>=', CarbonImmutable::parse((string) $filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('scheduled_start_at', '<=', CarbonImmutable::parse((string) $filters['to'])->endOfDay());
        }

        return $query;
    }

    private function deleteReplacedInstallationPhoto(?string $previous, ?string $current): void
    {
        if ($previous !== null && $previous !== '' && $previous !== $current) {
            Storage::disk('public')->delete($previous);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?ServiceOrder $existing = null): array
    {
        $payload = Arr::only($data, [
            'priority',
            'client_id',
            'vehicle_id',
            'equipment_id',
            'technician_id',
            'address',
            'scheduled_start_at',
            'scheduled_end_at',
            'estimated_duration_minutes',
            'description',
            'notes',
            'installation_location',
            'installation_location_photo_path',
            'execution_notes',
        ]);

        if (array_key_exists('address', $payload)) {
            $payload['address'] = filled($payload['address']) ? trim((string) $payload['address']) : null;
        }

        foreach (['installation_location', 'installation_location_photo_path'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = filled($payload[$field]) ? trim((string) $payload[$field]) : null;
            }
        }

        if (isset($payload['priority'])) {
            $payload['priority'] = ServiceOrderPriority::from((string) $payload['priority']);
        } elseif ($existing === null) {
            $payload['priority'] = ServiceOrderPriority::NORMAL;
        }

        if ($existing === null && empty($payload['client_id'])) {
            throw ValidationException::withMessages(['client_id' => ['O cliente é obrigatório.']]);
        }

        if (array_key_exists('scheduled_start_at', $payload) && $payload['scheduled_start_at']) {
            $payload['scheduled_start_at'] = CarbonImmutable::parse($payload['scheduled_start_at']);
        }

        if (array_key_exists('scheduled_end_at', $payload) && $payload['scheduled_end_at']) {
            $payload['scheduled_end_at'] = CarbonImmutable::parse($payload['scheduled_end_at']);
        }

        $start = $payload['scheduled_start_at'] ?? $existing?->scheduled_start_at;
        $end = $payload['scheduled_end_at'] ?? $existing?->scheduled_end_at;

        if ($start && $end && CarbonImmutable::parse($end)->lessThanOrEqualTo(CarbonImmutable::parse($start))) {
            throw ValidationException::withMessages([
                'scheduled_end_at' => ['O horário final deve ser posterior ao inicial.'],
            ]);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertRelations(array $payload, ?ServiceOrder $existing = null): void
    {
        $clientId = $payload['client_id'] ?? $existing?->client_id;
        $vehicleId = array_key_exists('vehicle_id', $payload) ? $payload['vehicle_id'] : $existing?->vehicle_id;
        $equipmentId = array_key_exists('equipment_id', $payload) ? $payload['equipment_id'] : $existing?->equipment_id;
        $technicianId = array_key_exists('technician_id', $payload) ? $payload['technician_id'] : $existing?->technician_id;
        $equipment = null;
        $technician = null;

        if ($clientId) {
            $client = Client::query()->find($clientId);
            if ($client === null) {
                throw ValidationException::withMessages(['client_id' => ['Cliente inválido.']]);
            }
        }

        if ($vehicleId) {
            $vehicle = Vehicle::query()->find($vehicleId);
            if ($vehicle === null || (int) $vehicle->client_id !== (int) $clientId) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['O veículo deve pertencer ao cliente selecionado.'],
                ]);
            }
        }

        if ($equipmentId) {
            $equipment = Equipment::query()->find($equipmentId);
            if ($equipment === null) {
                throw ValidationException::withMessages(['equipment_id' => ['Dispositivo inválido.']]);
            }

            if ($vehicleId && $equipment->vehicle_id !== null && (int) $equipment->vehicle_id !== (int) $vehicleId) {
                throw ValidationException::withMessages([
                    'equipment_id' => ['O dispositivo não está vinculado ao veículo selecionado.'],
                ]);
            }
        }

        if ($technicianId) {
            $technician = User::query()->find($technicianId);
            if ($technician === null || $technician->client_id !== null) {
                throw ValidationException::withMessages([
                    'technician_id' => ['Técnico inválido.'],
                ]);
            }
        }

        // O técnico com estoque vinculado só pode receber equipamentos desse
        // estoque. Técnico sem estoque mantém o comportamento anterior e pode
        // usar qualquer equipamento disponível.
        if (
            $equipment !== null
            && $technician !== null
            && $technician->stock_id !== null
            && (int) $equipment->stock_id !== (int) $technician->stock_id
        ) {
            throw ValidationException::withMessages([
                'equipment_id' => ['O equipamento não pertence ao estoque do técnico responsável.'],
            ]);
        }
    }

    /**
     * Campos calculados do agendamento: endereço e coordenadas do atendimento,
     * duração pelos serviços, deslocamento a partir da base do técnico, fim
     * previsto e reinício do lembrete quando o horário muda.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function deriveScheduling(array $payload, array $data, ?ServiceOrder $existing = null): array
    {
        $derived = [];
        $clientId = $payload['client_id'] ?? $existing?->client_id;
        $clientChanged = $existing !== null && array_key_exists('client_id', $payload)
            && (int) $payload['client_id'] !== (int) $existing->client_id;
        $coordinates = $existing ? Coordinates::tryFrom($existing->latitude, $existing->longitude) : null;
        $locationChanged = false;

        if (array_key_exists('address', $payload) && $payload['address'] !== null) {
            if ($payload['address'] !== $existing?->address) {
                $coordinates = $this->scheduling->geocode($payload['address']);
                $locationChanged = true;
            }
        } elseif ($existing === null || $clientChanged || array_key_exists('address', $payload)) {
            $client = $clientId ? Client::query()->find($clientId) : null;
            $location = $client ? $this->scheduling->clientLocation($client) : ['address' => '', 'coordinates' => null];
            $derived['address'] = $location['address'] !== '' ? $location['address'] : null;
            $coordinates = $location['coordinates'];
            $locationChanged = true;
        }

        if ($locationChanged) {
            $derived['latitude'] = $coordinates?->latitude;
            $derived['longitude'] = $coordinates?->longitude;
        }

        $duration = ! empty($payload['estimated_duration_minutes'])
            ? TimeGrid::minutes((int) $payload['estimated_duration_minutes'])
            : null;

        if ($duration === null && is_array($data['items'] ?? null)) {
            $duration = $this->scheduling->durationForItems($data['items']);
        }

        $duration ??= $existing?->estimated_duration_minutes;
        $duration = $duration !== null ? TimeGrid::minutes((int) $duration) : null;
        $derived['estimated_duration_minutes'] = $duration;

        $startInput = $payload['scheduled_start_at'] ?? null;
        $start = $startInput
            ? TimeGrid::ceil(CarbonImmutable::parse($startInput))
            : $existing?->scheduled_start_at;

        if ($startInput !== null && $start !== null) {
            $derived['scheduled_start_at'] = $start->toIso8601String();
        }

        $startChanged = array_key_exists('scheduled_start_at', $payload)
            && $start?->toIso8601String() !== $existing?->scheduled_start_at?->toIso8601String();

        $endInput = $payload['scheduled_end_at'] ?? null;
        $endProvided = ! empty($endInput);

        if ($endProvided) {
            $derived['scheduled_end_at'] = TimeGrid::ceil(CarbonImmutable::parse($endInput));
        } elseif ($start && $duration && ($existing?->scheduled_end_at === null || $startChanged)) {
            $derived['scheduled_end_at'] = $start->addMinutes($duration);
        }

        if ($existing !== null && $startChanged) {
            $derived['reminder_sent_at'] = null;
        }

        return $derived;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertScheduleConflict(array $payload, ?ServiceOrder $existing = null, bool $ignore = false): void
    {
        if ($ignore) {
            return;
        }

        $technicianId = array_key_exists('technician_id', $payload) ? $payload['technician_id'] : $existing?->technician_id;
        $start = $payload['scheduled_start_at'] ?? $existing?->scheduled_start_at;

        if (! $technicianId || ! $start) {
            return;
        }

        $startAt = CarbonImmutable::parse($start);
        $end = $payload['scheduled_end_at'] ?? $existing?->scheduled_end_at;
        $duration = $payload['estimated_duration_minutes'] ?? $existing?->estimated_duration_minutes;
        $endAt = $end
            ? CarbonImmutable::parse($end)
            : $startAt->addMinutes($duration ?: (int) config('scheduling.default_duration_minutes', 60));
        $latitude = array_key_exists('latitude', $payload) ? $payload['latitude'] : $existing?->latitude;
        $longitude = array_key_exists('longitude', $payload) ? $payload['longitude'] : $existing?->longitude;
        $coordinates = Coordinates::tryFrom($latitude, $longitude);

        $issues = $this->scheduling->availabilityIssues(
            (int) $technicianId,
            $startAt,
            $endAt,
            $coordinates,
            $existing?->getKey(),
        );

        if ($issues !== []) {
            throw ValidationException::withMessages([
                'scheduled_start_at' => [implode(' ', $issues).' Ajuste o horário ou confirme o conflito.'],
            ]);
        }
    }

    private function notifyScheduleChanges(
        ServiceOrder $order,
        ?int $previousTechnicianId,
        ?string $previousStart,
        ?string $previousAddress,
    ): void {
        $currentTechnicianId = $order->technician_id ? (int) $order->technician_id : null;

        if ($currentTechnicianId !== $previousTechnicianId) {
            if ($previousTechnicianId !== null) {
                $this->notifications->notifyUnassigned($order, $previousTechnicianId);
            }

            if ($currentTechnicianId !== null) {
                $this->notifications->notifyAssigned($order);
            }

            return;
        }

        if (
            $currentTechnicianId !== null
            && ($order->scheduled_start_at?->toIso8601String() !== $previousStart || $order->address !== $previousAddress)
        ) {
            $this->notifications->notifyRescheduled($order);
        }
    }

    private function nextNumber(): int
    {
        $tenantId = TenantContext::tenantId();

        $max = ServiceOrder::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('number');

        return ((int) $max) + 1;
    }
}
