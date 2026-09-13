<?php

namespace App\Modules\VehicleData\Http\Controllers;

use App\Modules\Shared\Http\ApiError;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\VehicleData\Exceptions\PlateLookupException;
use App\Modules\VehicleData\Http\Requests\LookupVehicleDataRequest;
use App\Modules\VehicleData\Http\Resources\VehicleDataResource;
use App\Modules\VehicleData\Support\PlateLookupResolver;
use Illuminate\Http\JsonResponse;

class VehicleDataController extends ApiController
{
    public function __construct(private readonly PlateLookupResolver $resolver) {}

    public function lookup(LookupVehicleDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        try {
            $data = $this->resolver->resolve()->lookup($request->validated('plate'));
        } catch (PlateLookupException $exception) {
            return ApiError::response($exception->getMessage(), $exception->statusCode);
        }

        return $this->success(VehicleDataResource::make($data), 'Consulta realizada com sucesso.');
    }
}
