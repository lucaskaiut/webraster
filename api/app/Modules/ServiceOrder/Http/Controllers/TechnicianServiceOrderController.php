<?php

namespace App\Modules\ServiceOrder\Http\Controllers;

use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Http\Requests\StoreServiceOrderAttachmentRequest;
use App\Modules\ServiceOrder\Http\Requests\TechnicianChangeStatusRequest;
use App\Modules\ServiceOrder\Http\Resources\ServiceOrderAttachmentResource;
use App\Modules\ServiceOrder\Http\Resources\TechnicianServiceOrderResource;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderAttachment;
use App\Modules\ServiceOrder\Services\ServiceOrderAttachmentService;
use App\Modules\Scheduling\Services\SchedulingService;
use App\Modules\Scheduling\Support\Coordinates;
use App\Modules\ServiceOrder\Services\ServiceOrderPdfService;
use App\Modules\ServiceOrder\Services\ServiceOrderService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Área "Minhas OS" do app: o técnico só acessa as OS atribuídas a ele.
 */
class TechnicianServiceOrderController extends ApiController
{
    private const RELATIONS = ['client', 'vehicle', 'equipment', 'technician', 'items', 'attachments.uploader'];

    public function __construct(
        private readonly ServiceOrderService $service,
        private readonly ServiceOrderAttachmentService $attachments,
        private readonly ServiceOrderPdfService $pdf,
        private readonly SchedulingService $scheduling,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $history = $request->string('scope')->toString() === 'history';

        $query = ServiceOrder::query()
            ->with(['client', 'vehicle', 'items'])
            ->where('technician_id', $request->user()->getKey());

        if ($history) {
            $query->whereIn('status', [ServiceOrderStatus::COMPLETED->value, ServiceOrderStatus::CANCELLED->value])
                ->orderByDesc('scheduled_start_at')
                ->orderByDesc('id');
        } else {
            $query->whereIn('status', [ServiceOrderStatus::OPEN->value, ServiceOrderStatus::IN_PROGRESS->value])
                ->orderByRaw('scheduled_start_at IS NULL')
                ->orderBy('scheduled_start_at');
        }

        if ($request->filled('from')) {
            $query->where('scheduled_start_at', '>=', CarbonImmutable::parse($request->string('from')->toString()));
        }

        if ($request->filled('to')) {
            $query->where('scheduled_start_at', '<=', CarbonImmutable::parse($request->string('to')->toString()));
        }

        $orders = $query->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return $this->paginated(TechnicianServiceOrderResource::collection($orders));
    }

    /**
     * Dashboard do técnico: contagens por situação e OS do período.
     * "Atrasadas" é uma categoria própria para OS abertas/iniciadas vencidas,
     * para que os segmentos do gráfico não se sobreponham.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $timezone = $this->scheduling->timezone();
        $from = CarbonImmutable::parse($data['from'], $timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($data['to'], $timezone)->endOfDay()->utc();

        $orders = ServiceOrder::query()
            ->with('client')
            ->where('technician_id', $request->user()->getKey())
            ->whereNotNull('scheduled_start_at')
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->orderBy('scheduled_start_at')
            ->orderBy('id')
            ->get();

        $counts = [
            'open' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'overdue' => 0,
        ];

        $items = $orders->map(function (ServiceOrder $order) use (&$counts) {
            $status = $order->status?->value ?? 'open';
            $dueAt = $order->scheduled_end_at
                ?? $order->scheduled_start_at?->addMinutes(
                    $order->estimated_duration_minutes
                        ?: (int) config('scheduling.default_duration_minutes', 60),
                );

            $category = match (true) {
                $status === ServiceOrderStatus::COMPLETED->value => 'completed',
                $status === ServiceOrderStatus::CANCELLED->value => 'cancelled',
                in_array($status, [
                    ServiceOrderStatus::OPEN->value,
                    ServiceOrderStatus::IN_PROGRESS->value,
                ], true) && $dueAt?->isPast() => 'overdue',
                $status === ServiceOrderStatus::IN_PROGRESS->value => 'in_progress',
                default => 'open',
            };

            $counts[$category]++;

            return [
                'id' => $order->uuid,
                'code' => $order->code,
                'status' => $status,
                'status_label' => $order->status?->label(),
                'category' => $category,
                'client' => $order->client ? [
                    'id' => $order->client->uuid,
                    'name' => $order->client->name,
                    'phone' => $order->client->phone,
                ] : null,
                'address' => $order->address,
                'scheduled_start_at' => $order->scheduled_start_at?->toIso8601String(),
                'scheduled_end_at' => $order->scheduled_end_at?->toIso8601String(),
                'estimated_duration_minutes' => $order->estimated_duration_minutes,
            ];
        })->values();

        return $this->success([
            'from' => $data['from'],
            'to' => $data['to'],
            'timezone' => $timezone,
            'counts' => $counts,
            'total' => $orders->count(),
            'orders' => $items,
        ]);
    }

    /**
     * Rota do dia do técnico: OS agendadas em ordem cronológica e o trajeto
     * calculado entre elas (base → primeira → ... → base).
     */
    public function day(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $timezone = $this->scheduling->timezone();
        $date = $data['date'] ?? CarbonImmutable::now($timezone)->format('Y-m-d');
        $day = CarbonImmutable::parse($date, $timezone);

        $technician = $request->user();
        $orders = ServiceOrder::query()
            ->with('client')
            ->where('technician_id', $technician->getKey())
            ->where('status', '!=', ServiceOrderStatus::CANCELLED->value)
            ->whereNotNull('scheduled_start_at')
            ->whereBetween('scheduled_start_at', [
                $day->startOfDay()->utc(),
                $day->endOfDay()->utc(),
            ])
            ->orderBy('scheduled_start_at')
            ->orderBy('id')
            ->get()
            ->values();

        $baseCoordinates = $this->scheduling->technicianBase($technician);
        $baseAddress = $technician->baseAddress() ?: null;
        $base = $baseCoordinates !== null ? [
            'kind' => 'base',
            'id' => null,
            'code' => null,
            'name' => 'Base do técnico',
            'address' => $baseAddress,
            'latitude' => $baseCoordinates->latitude,
            'longitude' => $baseCoordinates->longitude,
        ] : null;

        $stops = $orders->map(fn (ServiceOrder $order) => [
            'id' => $order->uuid,
            'code' => $order->code,
            'status' => $order->status?->value,
            'status_label' => $order->status?->label(),
            'client' => $order->client ? [
                'id' => $order->client->uuid,
                'name' => $order->client->name,
                'phone' => $order->client->phone,
            ] : null,
            'address' => $order->address,
            'latitude' => $order->latitude,
            'longitude' => $order->longitude,
            'scheduled_start_at' => $order->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $order->scheduled_end_at?->toIso8601String(),
            'estimated_duration_minutes' => $order->estimated_duration_minutes,
        ])->values();

        $legs = [];
        $previous = $base;
        $totalMinutes = 0;
        $totalDistance = 0;

        foreach ($orders as $order) {
            $current = $this->routeLocation($order);

            if ($previous !== null && $current !== null) {
                $from = Coordinates::tryFrom($previous['latitude'], $previous['longitude']);
                $to = Coordinates::tryFrom($current['latitude'], $current['longitude']);
                $route = $this->scheduling->routeBetween($from, $to);

                if ($route !== null) {
                    $legs[] = [
                        'from' => $previous,
                        'to' => $current,
                        'minutes' => $route->minutes,
                        'distance_meters' => $route->distanceMeters,
                        'source' => $route->source,
                        'geometry' => $route->geometry,
                    ];
                    $totalMinutes += $route->minutes;
                    $totalDistance += $route->distanceMeters;
                }

                $previous = $current;
            }
        }

        if ($previous !== null && $base !== null) {
            $from = Coordinates::tryFrom($previous['latitude'], $previous['longitude']);
            $to = Coordinates::tryFrom($base['latitude'], $base['longitude']);
            $route = $this->scheduling->routeBetween($from, $to);

            if ($route !== null) {
                $legs[] = [
                    'from' => $previous,
                    'to' => $base,
                    'minutes' => $route->minutes,
                    'distance_meters' => $route->distanceMeters,
                    'source' => $route->source,
                    'geometry' => $route->geometry,
                ];
                $totalMinutes += $route->minutes;
                $totalDistance += $route->distanceMeters;
            }
        }

        return $this->success([
            'date' => $date,
            'timezone' => $timezone,
            'technician' => [
                'id' => $technician->uuid,
                'name' => $technician->name,
            ],
            'base' => $base,
            'stops' => $stops,
            'legs' => $legs,
            'total_minutes' => $totalMinutes,
            'total_distance_meters' => $totalDistance,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routeLocation(ServiceOrder $order): ?array
    {
        if ($order->latitude === null || $order->longitude === null) {
            return null;
        }

        return [
            'kind' => 'order',
            'id' => $order->uuid,
            'code' => $order->code,
            'name' => $order->client?->name,
            'address' => $order->address,
            'latitude' => $order->latitude,
            'longitude' => $order->longitude,
        ];
    }

    public function show(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->assertOwn($request, $serviceOrder);

        return $this->success(TechnicianServiceOrderResource::make($serviceOrder->load(self::RELATIONS)));
    }

    public function changeStatus(TechnicianChangeStatusRequest $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->assertOwn($request, $serviceOrder);

        $data = $request->validated();
        $order = $this->service->changeStatus(
            $serviceOrder,
            ServiceOrderStatus::from((string) $data['status']),
            $request->user(),
            null,
            $data['execution_notes'] ?? null,
        );

        return $this->success(TechnicianServiceOrderResource::make($order->load(self::RELATIONS)), 'Status atualizado.');
    }

    public function pdf(Request $request, ServiceOrder $serviceOrder): StreamedResponse
    {
        $this->assertOwn($request, $serviceOrder);

        return $this->pdf->download($serviceOrder);
    }

    public function storeAttachment(StoreServiceOrderAttachmentRequest $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->assertOwn($request, $serviceOrder);

        $attachment = $this->attachments->store($serviceOrder, $request->file('file'), $request->user());

        return $this->created(ServiceOrderAttachmentResource::make($attachment), 'Anexo adicionado.');
    }

    public function downloadAttachment(
        Request $request,
        ServiceOrder $serviceOrder,
        ServiceOrderAttachment $attachment,
    ): StreamedResponse {
        $this->assertOwn($request, $serviceOrder);

        return $this->attachments->download($serviceOrder, $attachment);
    }

    private function assertOwn(Request $request, ServiceOrder $order): void
    {
        if ((int) $order->technician_id !== (int) $request->user()->getKey()) {
            throw (new ModelNotFoundException)->setModel(ServiceOrder::class, [$order->uuid]);
        }
    }
}
