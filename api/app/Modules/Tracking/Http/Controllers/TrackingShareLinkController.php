<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tracking\Http\Requests\StoreTrackingShareLinkRequest;
use App\Modules\Tracking\Services\TrackingShareLinkService;
use Illuminate\Http\JsonResponse;

class TrackingShareLinkController extends ApiController
{
    public function __construct(private readonly TrackingShareLinkService $service) {}

    public function store(StoreTrackingShareLinkRequest $request): JsonResponse
    {
        ['link' => $link, 'token' => $token] = $this->service->create(
            $request->user(),
            $request->validated('vehicle_ids'),
            (int) $request->validated('expires_in_hours'),
        );

        return $this->success([
            'id' => $link->uuid,
            'token' => $token,
            'expires_at' => $link->expires_at->toIso8601String(),
            'vehicles' => $link->vehicles->map(fn ($vehicle) => [
                'id' => $vehicle->uuid,
                'plate' => $vehicle->plate,
            ])->values(),
        ], 'Link de compartilhamento criado.', 201);
    }
}
