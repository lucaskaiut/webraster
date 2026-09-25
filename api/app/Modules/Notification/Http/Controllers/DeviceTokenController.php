<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Modules\Notification\Http\Requests\StoreDeviceTokenRequest;
use App\Modules\Notification\Http\Resources\DeviceTokenResource;
use App\Modules\Notification\Models\DeviceToken;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends ApiController
{
    /**
     * Registra (ou atualiza) o token de push do dispositivo do usuário logado.
     */
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $user = $request->user();

        $token = DeviceToken::query()
            ->withoutGlobalScopes()
            ->updateOrCreate(
                ['token' => $request->validated('token')],
                [
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->getKey(),
                    'platform' => $request->validated('platform'),
                ],
            );

        return $this->created(DeviceTokenResource::make($token), 'Dispositivo registrado.');
    }

    /**
     * Remove um token do próprio usuário (logout, troca de aparelho).
     */
    public function destroy(Request $request, DeviceToken $deviceToken): JsonResponse
    {
        abort_unless(
            (int) $deviceToken->user_id === (int) $request->user()->getKey(),
            404,
        );

        $deviceToken->delete();

        return $this->success(null, 'Dispositivo removido.');
    }
}
