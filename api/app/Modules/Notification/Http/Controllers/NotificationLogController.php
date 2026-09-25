<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Modules\Alert\Models\UserNotification;
use App\Modules\Notification\Http\Requests\NotificationLogRequest;
use App\Modules\Notification\Http\Resources\NotificationLogResource;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationLogController extends ApiController
{
    /**
     * Histórico de notificações enviadas (log para o painel).
     */
    public function index(NotificationLogRequest $request): JsonResponse
    {
        $query = UserNotification::query()
            ->with(['user', 'deliveries'])
            ->orderByDesc('created_at');

        if ($source = $request->validated('source')) {
            $query->where('source', $source);
        }

        if ($type = $request->validated('type')) {
            $query->where('type', $type);
        }

        if ($userId = $request->validated('user_id')) {
            $query->whereHas('user', fn ($user) => $user->where('uuid', $userId));
        }

        if ($request->has('clicked')) {
            $request->boolean('clicked')
                ? $query->whereNotNull('clicked_at')
                : $query->whereNull('clicked_at');
        }

        if ($from = $request->validated('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->validated('to')) {
            $query->where('created_at', '<=', $to);
        }

        $perPage = (int) $request->integer('per_page', 20);

        return $this->paginated(
            NotificationLogResource::collection($query->paginate(max(1, min(100, $perPage)))),
        );
    }

    /**
     * Registra que o usuário abriu a notificação (clique no push ou na caixa).
     */
    public function click(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless(
            (int) $notification->user_id === (int) $request->user()->getKey(),
            404,
        );

        $notification->markAsClicked();

        return $this->success([
            'clicked_at' => $notification->clicked_at?->toIso8601String(),
        ]);
    }
}
