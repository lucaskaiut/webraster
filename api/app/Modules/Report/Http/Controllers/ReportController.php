<?php

namespace App\Modules\Report\Http\Controllers;

use App\Modules\Report\Services\ReportService;
use App\Modules\Report\Support\Report;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends ApiController
{
    public function __construct(private readonly ReportService $service) {}

    public function commands(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        return $this->success($this->service->commands($this->filters($request)));
    }

    public function commandsExport(Request $request): StreamedResponse
    {
        $this->authorize('export', Report::class);

        return $this->service->commandsExport($this->filters($request));
    }

    public function positions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        return $this->success($this->service->positions($this->filters($request)));
    }

    public function positionsExport(Request $request): StreamedResponse
    {
        $this->authorize('export', Report::class);

        return $this->service->positionsExport($this->filters($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'client_id' => $request->string('client_id')->toString() ?: null,
            'vehicle_id' => $request->string('vehicle_id')->toString() ?: null,
        ];
    }
}
