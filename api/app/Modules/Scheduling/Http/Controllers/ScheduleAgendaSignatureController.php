<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Modules\Scheduling\Http\Requests\ScheduleAgendaSignatureRequest;
use App\Modules\Scheduling\Services\SchedulingService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class ScheduleAgendaSignatureController extends ApiController
{
    public function __construct(private readonly SchedulingService $scheduling) {}

    /**
     * Assinatura da agenda do técnico por dia, para o painel detectar OS
     * marcadas/alteradas por outras pessoas e recalcular só os dias afetados.
     */
    public function __invoke(ScheduleAgendaSignatureRequest $request): JsonResponse
    {
        $data = $request->validated();

        return $this->success([
            'days' => $this->scheduling->agendaSignatures(
                (int) $data['technician_id'],
                $data['from'],
                $data['to'],
                isset($data['service_order_id']) ? (int) $data['service_order_id'] : null,
            ),
        ]);
    }
}
