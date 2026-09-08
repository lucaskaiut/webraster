<?php

namespace App\Modules\ServiceOrder\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\ServiceOrder\Enums\ServiceOrderHistoryAction;
use App\Modules\ServiceOrder\Enums\ServiceOrderPriority;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Enums\ServiceOrderType;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceOrderService
{
    public function __construct(
        private readonly ServiceOrderHistoryService $history,
        private readonly ServiceOrderNotificationService $notifications,
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
        $this->assertScheduleConflict($payload, ignore: (bool) ($data['ignore_schedule_conflict'] ?? false));

        $order = DB::transaction(function () use ($payload, $actor) {
            $payload['number'] = $this->nextNumber();
            $payload['status'] = ServiceOrderStatus::OPEN;
            $payload['created_by'] = $actor->getKey();

            $order = ServiceOrder::query()->create($payload);

            $this->history->record($order, ServiceOrderHistoryAction::CREATED, $actor);

            return $order;
        });

        $order->load(['client', 'vehicle', 'equipment', 'technician', 'creator']);

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
        $this->assertScheduleConflict($payload, $order, (bool) ($data['ignore_schedule_conflict'] ?? false));

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

        $order->fill($payload);
        $order->save();

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

        if (isset($changes['technician_id']) && $order->technician_id) {
            $this->notifications->notifyAssigned($order->fresh() ?? $order);
        }

        return $order->refresh()->load(['client', 'vehicle', 'equipment', 'technician', 'creator']);
    }

    public function changeStatus(
        ServiceOrder $order,
        ServiceOrderStatus $next,
        User $actor,
        ?string $cancellationReason = null,
        ?string $executionNotes = null,
    ): ServiceOrder {
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

        $this->notifications->notifyStatusChanged(
            $order,
            $previous->label(),
            $next->label(),
        );

        return $order->refresh()->load(['client', 'vehicle', 'equipment', 'technician', 'creator', 'completer', 'canceller']);
    }

    public function delete(ServiceOrder $order): void
    {
        if ($order->status === ServiceOrderStatus::IN_PROGRESS) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível excluir uma OS em andamento. Cancele-a primeiro.'],
            ]);
        }

        $order->delete();
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
                    ->orWhere('notes', 'like', "%{$search}%");

                if (preg_match('/^OS-?0*(\d+)$/i', $search, $matches)) {
                    $builder->orWhere('number', (int) $matches[1]);
                } elseif (ctype_digit($search)) {
                    $builder->orWhere('number', (int) $search);
                }
            });
        }

        foreach (['status', 'type', 'priority'] as $field) {
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?ServiceOrder $existing = null): array
    {
        $payload = Arr::only($data, [
            'type',
            'priority',
            'client_id',
            'vehicle_id',
            'equipment_id',
            'technician_id',
            'scheduled_start_at',
            'scheduled_end_at',
            'description',
            'notes',
            'execution_notes',
        ]);

        if (isset($payload['type'])) {
            $payload['type'] = ServiceOrderType::from((string) $payload['type']);
        } elseif ($existing === null) {
            throw ValidationException::withMessages(['type' => ['O tipo é obrigatório.']]);
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
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertScheduleConflict(array $payload, ?ServiceOrder $existing = null, bool $ignore = false): void
    {
        $technicianId = $payload['technician_id'] ?? $existing?->technician_id;
        $start = $payload['scheduled_start_at'] ?? $existing?->scheduled_start_at;
        $end = $payload['scheduled_end_at'] ?? $existing?->scheduled_end_at;

        if (! $technicianId || ! $start) {
            return;
        }

        $startAt = CarbonImmutable::parse($start);
        $endAt = $end ? CarbonImmutable::parse($end) : $startAt->addHour();

        $conflict = ServiceOrder::query()
            ->where('technician_id', $technicianId)
            ->whereNotIn('status', [ServiceOrderStatus::CANCELLED->value, ServiceOrderStatus::COMPLETED->value])
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->getKey()))
            ->whereNotNull('scheduled_start_at')
            ->where(function ($query) use ($startAt, $endAt): void {
                $query->where(function ($q) use ($startAt, $endAt): void {
                    $q->where('scheduled_start_at', '<', $endAt)
                        ->where(function ($inner) use ($startAt): void {
                            $inner->whereNull('scheduled_end_at')
                                ->orWhere('scheduled_end_at', '>', $startAt);
                        });
                });
            })
            ->with('client')
            ->first();

        if ($conflict && ! $ignore) {
            throw ValidationException::withMessages([
                'scheduled_start_at' => [sprintf(
                    'Conflito de agenda com %s (%s). Ajuste o horário ou confirme o conflito.',
                    $conflict->code,
                    $conflict->client?->name ?? 'OS',
                )],
            ]);
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
