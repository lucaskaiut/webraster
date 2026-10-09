<?php

namespace App\Modules\Alert\Http\Controllers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Alert\Services\AlertSoundService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Som das notificações por grupo de alerta, escolhido pelo próprio usuário
 * no app. Aceita também operadores (sem cliente): a preferência só afeta as
 * notificações do próprio usuário.
 */
class AlertSoundPreferenceController extends ApiController
{
    public function __construct(private readonly AlertSoundService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->userFor($request);

        return $this->success([
            'sounds' => $this->service->availableSounds(),
            'alerts' => $this->service->alertsForUser($user),
        ]);
    }

    public function update(Request $request, string $type): JsonResponse
    {
        $user = $this->userFor($request);

        abort_unless(in_array($type, AlertSoundService::groupKeys(), true), 404);

        $validated = $request->validate([
            'sound' => ['required', 'string', Rule::in($this->service->availableSounds())],
        ]);

        $preference = $this->service->set($user, $type, (string) $validated['sound']);

        return $this->success([
            'type' => $preference->type,
            'sound' => $preference->sound,
        ], 'Som do alerta atualizado.');
    }

    private function userFor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user?->hasPermission(Permission::ALERT_READ) ?? false, 403);

        return $user;
    }
}
