<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

abstract class ApiController extends Controller
{
    use AuthorizesRequests;

    /**
     * Para requests autenticados via API token, o middleware EnsurePermission
     * já validou os escopos — a verificação do Gate é redundante e quebraria
     * porque não há usuário autenticado (token é machine-to-machine).
     */
    public function authorize($ability, $arguments = []): void
    {
        if (request()->attributes->get('api_token')) {
            return;
        }

        Gate::forUser(request()->user())->authorize($ability, $arguments);
    }

    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function paginated(AnonymousResourceCollection $collection, ?string $message = null): JsonResponse
    {
        $payload = $collection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $payload['data'] ?? [],
            'meta' => $payload['meta'] ?? null,
            'links' => $payload['links'] ?? null,
        ]);
    }
}
