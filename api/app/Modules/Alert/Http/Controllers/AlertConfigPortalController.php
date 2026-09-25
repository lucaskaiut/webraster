<?php

namespace App\Modules\Alert\Http\Controllers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Http\Resources\ClientAlertConfigResource;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Client\Models\Client;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuração de alertas pelo cliente final no portal: apenas liga/desliga
 * cada alerta do subconjunto simples, sempre no escopo do próprio cliente.
 */
class AlertConfigPortalController extends ApiController
{
    public function __construct(private readonly AlertConfigService $service) {}

    public function index(Request $request): JsonResponse
    {
        $client = $this->clientFor($request);

        return $this->success(
            ClientAlertConfigResource::collection($this->service->clientConfigurations($client)),
        );
    }

    public function update(Request $request, string $type): JsonResponse
    {
        $client = $this->clientFor($request);

        $configurable = array_map(
            fn (AlertType $item) => $item->value,
            AlertType::clientConfigurable(),
        );

        abort_unless(in_array($type, $configurable, true), 404);

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $config = $this->service->setClientEnabled(
            $client,
            AlertType::from($type),
            (bool) $validated['is_enabled'],
        );

        return $this->success(
            ClientAlertConfigResource::make($config),
            'Preferência de alerta atualizada.',
        );
    }

    private function clientFor(Request $request): Client
    {
        $user = $request->user();

        abort_unless($user?->hasPermission(Permission::ALERT_READ) ?? false, 403);
        abort_unless($user->client_id !== null, 403);

        return Client::query()->findOrFail($user->client_id);
    }
}
