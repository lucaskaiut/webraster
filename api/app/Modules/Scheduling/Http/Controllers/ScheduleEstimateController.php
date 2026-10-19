<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\Scheduling\Http\Requests\ScheduleEstimateRequest;
use App\Modules\Scheduling\Services\SchedulingService;
use App\Modules\Scheduling\Support\TimeGrid;
use App\Modules\Scheduling\Support\TravelEstimate;
use App\Modules\Shared\Http\Controllers\ApiController;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class ScheduleEstimateController extends ApiController
{
    public function __construct(private readonly SchedulingService $scheduling) {}

    /**
     * Estima execução e deslocamento e sugere horários livres do técnico no dia.
     */
    public function __invoke(ScheduleEstimateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $warnings = [];

        $address = trim((string) ($data['address'] ?? ''));
        $coordinates = null;

        if ($address !== '') {
            $coordinates = $this->scheduling->geocode($address);
        } elseif (! empty($data['client_id'])) {
            $client = Client::query()->find($data['client_id']);

            if ($client !== null) {
                ['address' => $address, 'coordinates' => $coordinates] = $this->scheduling->clientLocation($client);
            }
        }

        if ($address !== '' && $coordinates === null) {
            $warnings[] = 'Não foi possível localizar o endereço do atendimento no mapa; o deslocamento não foi considerado.';
        }

        $duration = ! empty($data['duration_minutes'])
            ? (int) $data['duration_minutes']
            : $this->scheduling->durationForItems($data['items'] ?? []);
        $durationSource = ! empty($data['duration_minutes']) ? 'manual' : ($duration ? 'services' : 'default');
        $duration ??= (int) config('scheduling.default_duration_minutes', 60);
        $duration = TimeGrid::minutes(max(0, $duration));

        $technicianId = ! empty($data['technician_id']) ? (int) $data['technician_id'] : null;
        $technician = $this->scheduling->technician($technicianId);

        if ($technicianId !== null && $technician === null) {
            $warnings[] = 'O técnico está inativo ou não possui endereço-base e jornada; o deslocamento não foi considerado e a jornada padrão é 08:00–18:00.';
        }

        $slots = $technicianId !== null
            ? $this->scheduling->suggestSlots(
                $technicianId,
                $data['date'],
                $duration,
                $coordinates,
                $data['service_order_id'] ?? null,
            )
            : [];

        $previewStart = ! empty($data['scheduled_start_at'])
            ? CarbonImmutable::parse($data['scheduled_start_at'])
            : null;

        if ($previewStart === null) {
            $previewSlot = collect($slots)->firstWhere('available', true)
                ?? collect($slots)->first();

            if ($previewSlot !== null) {
                $previewStart = CarbonImmutable::parse($previewSlot['start']);
            }
        }

        $previewEnd = $previewStart?->addMinutes($duration);

        $preview = $previewStart !== null && $previewEnd !== null
            ? $this->scheduling->previewTravel(
                (int) $technicianId,
                $previewStart,
                $previewEnd,
                $coordinates,
                $data['service_order_id'] ?? null,
            )
            : null;

        $travel = $preview['from_travel'] ?? null;
        $returnTravel = $preview['to_travel'] ?? null;
        $baseAddress = $technician?->baseAddress() ?: null;
        $originOrder = $preview['from_order'] ?? null;
        $returnOrder = $preview['to_order'] ?? null;
        $originCoordinates = $preview['from_origin'] ?? null;
        $returnCoordinates = $preview['to_destination'] ?? null;
        $originAddress = $originOrder?->address ?: $baseAddress;
        $returnAddress = $returnOrder?->address ?: $baseAddress;

        return $this->success([
            'address' => $address !== '' ? $address : null,
            'latitude' => $coordinates?->latitude,
            'longitude' => $coordinates?->longitude,
            'duration_minutes' => $duration,
            'duration_source' => $durationSource,
            'travel' => $travel?->toArray(),
            'travel_return' => $returnTravel?->toArray(),
            'travel_round_trip' => $travel && $returnTravel ? [
                'minutes' => $travel->minutes + $returnTravel->minutes,
                'distance_meters' => $travel->distanceMeters + $returnTravel->distanceMeters,
                'source' => $travel->source === $returnTravel->source
                    ? $travel->source
                    : TravelEstimate::SOURCE_ESTIMATE,
            ] : null,
            'travel_origin' => $originAddress,
            'travel_origin_kind' => $originOrder !== null ? 'order' : 'base',
            'travel_origin_order_code' => $originOrder?->code,
            'travel_return_address' => $returnAddress,
            'travel_return_kind' => $returnOrder !== null ? 'order' : 'base',
            'travel_return_order_code' => $returnOrder?->code,
            'route' => $travel || $returnTravel ? [
                'origin' => $originCoordinates ? [
                    'latitude' => $originCoordinates->latitude,
                    'longitude' => $originCoordinates->longitude,
                    'address' => $originAddress,
                    'kind' => $originOrder !== null ? 'order' : 'base',
                    'order_code' => $originOrder?->code,
                ] : null,
                'destination' => $coordinates ? [
                    'latitude' => $coordinates->latitude,
                    'longitude' => $coordinates->longitude,
                    'address' => $address !== '' ? $address : null,
                    'kind' => 'destination',
                    'order_code' => null,
                ] : null,
                'return_to' => $returnCoordinates ? [
                    'latitude' => $returnCoordinates->latitude,
                    'longitude' => $returnCoordinates->longitude,
                    'address' => $returnAddress,
                    'kind' => $returnOrder !== null ? 'order' : 'base',
                    'order_code' => $returnOrder?->code,
                ] : null,
                'outbound' => $travel?->geometry ?? [],
                'return' => $returnTravel?->geometry ?? [],
            ] : null,
            'date' => $data['date'],
            'timezone' => $this->scheduling->timezone(),
            'slots' => $slots,
            'warnings' => $warnings,
        ]);
    }
}
