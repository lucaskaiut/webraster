<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Models\UserNotification;
use App\Modules\Notification\Enums\NotificationChannel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function paginate(int $userId, int $perPage = 20, ?bool $unreadOnly = null): LengthAwarePaginator
    {
        return $this->inboxQuery($userId)
            ->with(['alert.vehicle'])
            ->when($unreadOnly === true, fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function unreadCount(int $userId): int
    {
        return $this->inboxQuery($userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(UserNotification $notification): UserNotification
    {
        $notification->markAsRead();

        return $notification->refresh();
    }

    public function markAllRead(int $userId): int
    {
        return $this->inboxQuery($userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Caixa de entrada = notificações que tiveram entrega in-app (push puro
     * não aparece no sino; segue apenas no histórico).
     */
    private function inboxQuery(int $userId)
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereHas('deliveries', fn ($query) => $query->where('channel', NotificationChannel::IN_APP->value));
    }
}
