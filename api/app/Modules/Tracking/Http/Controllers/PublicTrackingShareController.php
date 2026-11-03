<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tracking\Http\Resources\PublicTrackingVehicleResource;
use App\Modules\Tracking\Services\TrackingShareLinkService;
use Illuminate\Http\JsonResponse;

/**
 * Link público de monitoramento: posição atual dos veículos compartilhados.
 */
class PublicTrackingShareController extends ApiController
{
    public function __construct(private readonly TrackingShareLinkService $service) {}

    public function __invoke(string $token): JsonResponse
    {
        $link = $this->service->findByToken($token);

        abort_if($link === null, 404, 'Link de compartilhamento não encontrado.');
        abort_if($link->isExpired(), 410, 'Este link de compartilhamento expirou.');

        $tenant = $link->tenant;

        return $this->success([
            'expires_at' => $link->expires_at->toIso8601String(),
            'tenant' => [
                'name' => $tenant?->name,
                'logo_url' => $tenant?->logoUrl(),
                'theme' => $tenant?->themeSettings(),
            ],
            'vehicles' => $this->service->positions($link)->map(
                fn (array $item) => new PublicTrackingVehicleResource(
                    $item['vehicle'],
                    $item['position'],
                    $item['online'],
                ),
            )->values(),
        ]);
    }
}
