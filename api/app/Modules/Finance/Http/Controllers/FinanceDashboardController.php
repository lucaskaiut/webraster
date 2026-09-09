<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Services\FinanceDashboardService;
use App\Modules\Finance\Support\FinanceDashboard;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class FinanceDashboardController extends ApiController
{
    public function __construct(private readonly FinanceDashboardService $service) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', FinanceDashboard::class);

        return $this->success($this->service->metrics());
    }
}
