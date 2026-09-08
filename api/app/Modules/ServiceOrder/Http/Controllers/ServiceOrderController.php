<?php

namespace App\Modules\ServiceOrder\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Http\Requests\ChangeServiceOrderStatusRequest;
use App\Modules\ServiceOrder\Http\Requests\StoreServiceOrderRequest;
use App\Modules\ServiceOrder\Http\Requests\UpdateServiceOrderRequest;
use App\Modules\ServiceOrder\Http\Resources\ServiceOrderHistoryResource;
use App\Modules\ServiceOrder\Http\Resources\ServiceOrderResource;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Services\ServiceOrderService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceOrderController extends ApiController
{
    public function __construct(private readonly ServiceOrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceOrder::class);

        $orders = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $this->filters($request),
        );

        return $this->paginated(ServiceOrderResource::collection($orders));
    }

    public function kanban(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceOrder::class);

        $grouped = $this->service->kanban($this->filters($request));

        $data = $grouped->map(fn ($items) => ServiceOrderResource::collection($items)->resolve());

        return $this->success($data);
    }

    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceOrder::class);

        $orders = $this->service->calendar($this->filters($request));

        return $this->success(ServiceOrderResource::collection($orders));
    }

    public function show(ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('view', $serviceOrder);

        $serviceOrder->load([
            'client',
            'vehicle',
            'equipment',
            'technician',
            'creator',
            'completer',
            'canceller',
            'histories.user',
        ]);

        return $this->success(ServiceOrderResource::make($serviceOrder));
    }

    public function store(StoreServiceOrderRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceOrder::class);

        $order = $this->service->create($request->validated(), $request->user());

        return $this->created(ServiceOrderResource::make($order), 'Ordem de serviço criada.');
    }

    public function update(UpdateServiceOrderRequest $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('update', $serviceOrder);

        $order = $this->service->update($serviceOrder, $request->validated(), $request->user());

        return $this->success(ServiceOrderResource::make($order), 'Ordem de serviço atualizada.');
    }

    public function destroy(ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('delete', $serviceOrder);

        $this->service->delete($serviceOrder);

        return $this->success(null, 'Ordem de serviço removida.');
    }

    public function changeStatus(ChangeServiceOrderStatusRequest $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('changeStatus', $serviceOrder);

        $data = $request->validated();
        $order = $this->service->changeStatus(
            $serviceOrder,
            ServiceOrderStatus::from((string) $data['status']),
            $request->user(),
            $data['cancellation_reason'] ?? null,
            $data['execution_notes'] ?? null,
        );

        return $this->success(ServiceOrderResource::make($order), 'Status atualizado.');
    }

    public function history(ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('view', $serviceOrder);

        $histories = $serviceOrder->histories()->with('user')->limit(100)->get();

        return $this->success(ServiceOrderHistoryResource::collection($histories));
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'type' => $request->string('type')->toString() ?: null,
            'priority' => $request->string('priority')->toString() ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ];

        if ($request->filled('client_id')) {
            $filters['client_id'] = Client::query()->where('uuid', $request->string('client_id')->toString())->value('id');
        }

        if ($request->filled('vehicle_id')) {
            $filters['vehicle_id'] = Vehicle::query()->where('uuid', $request->string('vehicle_id')->toString())->value('id');
        }

        if ($request->filled('technician_id')) {
            $filters['technician_id'] = User::query()->where('uuid', $request->string('technician_id')->toString())->value('id');
        }

        if ($request->filled('equipment_id')) {
            $filters['equipment_id'] = Equipment::query()->where('uuid', $request->string('equipment_id')->toString())->value('id');
        }

        return $filters;
    }
}
