<?php

namespace App\Modules\ExternalApi\Http\Controllers;

use App\Modules\ExternalApi\Http\Requests\ExternalApiPeriodRequest;
use App\Modules\ExternalApi\Http\Resources\ExternalApiRequestResource;
use App\Modules\ExternalApi\Models\ExternalApiRequest;
use App\Modules\ExternalApi\Services\ExternalApiMetricsService;
use App\Modules\ExternalApi\Support\ExternalApiCatalog;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class ExternalApiController extends ApiController
{
    public function __construct(
        private readonly ExternalApiMetricsService $metrics,
        private readonly ExternalApiCatalog $catalog,
    ) {}

    public function services(): JsonResponse
    {
        return $this->success($this->catalog->services());
    }

    public function metrics(ExternalApiPeriodRequest $request): JsonResponse
    {
        return $this->success($this->metrics->metrics(
            $request->from(),
            $request->to(),
            $request->granularity(),
            $request->timezone(),
            $request->services(),
        ));
    }

    public function index(ExternalApiPeriodRequest $request): JsonResponse
    {
        $requests = $this->metrics->paginate(
            $request->from(),
            $request->to(),
            [
                'service' => $request->validated('service'),
                'status' => $request->validated('status'),
                'search' => $request->validated('search'),
            ],
            (int) ($request->validated('per_page') ?? 20),
        );

        return $this->paginated(ExternalApiRequestResource::collection($requests));
    }

    public function show(ExternalApiRequest $externalApiRequest): JsonResponse
    {
        $externalApiRequest->load(['tenant:id,name', 'user:id,name']);

        return $this->success(
            (new ExternalApiRequestResource($externalApiRequest))->additional(['detailed' => true])->resolve(),
        );
    }
}
