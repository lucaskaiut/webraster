<?php

namespace App\Modules\Alert\Http\Controllers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Alert\Http\Resources\UserNotificationResource;
use App\Modules\Alert\Models\UserNotification;
use App\Modules\Alert\Services\NotificationService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permission::NOTIFICATION_READ)
            || $request->user()?->hasPermission(Permission::ALERT_READ), 403);

        $unreadOnly = $request->has('unread')
            ? $request->boolean('unread')
            : null;

        $notifications = $this->service->paginate(
            (int) $request->user()->getKey(),
            (int) $request->integer('per_page', 20),
            $unreadOnly,
        );

        return $this->paginated(UserNotificationResource::collection($notifications));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permission::NOTIFICATION_READ)
            || $request->user()?->hasPermission(Permission::ALERT_READ), 403);

        return $this->success([
            'count' => $this->service->unreadCount((int) $request->user()->getKey()),
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permission::NOTIFICATION_READ)
            || $request->user()?->hasPermission(Permission::ALERT_READ), 403);

        abort_unless((int) $notification->user_id === (int) $request->user()->getKey(), 404);

        return $this->success(
            UserNotificationResource::make($this->service->markRead($notification)),
            'Notificação marcada como lida.',
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permission::NOTIFICATION_READ)
            || $request->user()?->hasPermission(Permission::ALERT_READ), 403);

        $count = $this->service->markAllRead((int) $request->user()->getKey());

        return $this->success(['updated' => $count], 'Notificações marcadas como lidas.');
    }
}
