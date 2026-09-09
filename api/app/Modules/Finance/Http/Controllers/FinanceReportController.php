<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Services\FinanceReportService;
use App\Modules\Finance\Support\FinanceReport;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinanceReportController extends ApiController
{
    public function __construct(private readonly FinanceReportService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceReport::class);

        $type = $request->string('type')->toString();
        if ($type === '') {
            throw ValidationException::withMessages([
                'type' => ['Informe o tipo do relatório.'],
            ]);
        }

        $data = $this->service->report($type, [
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ]);

        return $this->success([
            'type' => $type,
            'rows' => $data,
        ]);
    }
}
