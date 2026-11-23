<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Modules\Scheduling\Http\Requests\UpdateWorkDayRequest;
use App\Modules\Scheduling\Services\SchedulingService;
use App\Modules\Scheduling\Services\WorkDayAdjustmentService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * App do técnico: consultar e ajustar (antecipar/estender) a própria jornada em um dia.
 */
class TechnicianWorkDayController extends ApiController
{
    public function __construct(
        private readonly WorkDayAdjustmentService $workDay,
        private readonly SchedulingService $scheduling,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = $data['date'] ?? CarbonImmutable::now($this->scheduling->timezone())->format('Y-m-d');

        return $this->success($this->workDay->show($request->user()->load('workHours'), $date));
    }

    public function update(UpdateWorkDayRequest $request): JsonResponse
    {
        $data = $request->validated();

        return $this->success(
            $this->workDay->update(
                $request->user()->load('workHours'),
                $data['date'],
                $data['starts_at'] ?? null,
                $data['ends_at'] ?? null,
            ),
            'Jornada do dia atualizada.',
        );
    }
}
