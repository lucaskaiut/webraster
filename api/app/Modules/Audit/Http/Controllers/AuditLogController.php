<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Http\Resources\AuditLogResource;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Shared\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends ApiController
{
    public function __construct(private readonly AuditLogService $service) {}

    public function index(Request $request): JsonResponse
    {
        $logs = $this->service->paginate(
            PerPage::fromRequest($request, 15),
            $request->string('action')->toString() ?: null,
        );

        return $this->paginated(AuditLogResource::collection($logs));
    }
}
